<?php

/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\OTel\InferredSpans;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SemConv\Version;
use WeakReference;

class InferredSpans
{
    use LogsMessagesTrait;

    private const METADATA_SPAN = 'span';
    private const METADATA_CONTEXT = 'context';
    private const METADATA_SCOPE = 'scope';
    private const METADATA_STACKTRACE_ID = 'stackTraceId';

    private const MILLIS_TO_NANOS = 1_000_000;
    private const FRAMES_TO_SKIP = 2;

    private TracerInterface $tracer;
    /** @var array<mixed> */
    private array $lastStackTrace;
    private int $stackTraceId = 0;

    private bool $shutdown;


    public function __construct(private readonly bool $spanReductionEnabled, private readonly bool $attachStackTrace, private readonly float $minSpanDuration)
    {
        $this->tracer = Globals::tracerProvider()->getTracer(
            'co.elastic.php.elastic-inferred-spans',
            null,
            Version::VERSION_1_25_0->url(),
        );

        self::logDebug('spanReductionEnabled ' . $spanReductionEnabled . ' attachStackTrace ' . $attachStackTrace . ' minSpanDuration ' . $minSpanDuration);

        $this->lastStackTrace = array();
        $this->shutdown = false;
    }

    // $durationMs - duration between interrupt request and interrupt occurence
    public function captureStackTrace(int $durationMs, bool $topFrameIsInternalFunction): void
    {
        self::logDebug("captureStackTrace topFrameInternal: $topFrameIsInternalFunction, duration: $durationMs ms shutdown: " . $this->shutdown);

        if ($this->shutdown) {
            return;
        }

        try {
            $stackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            array_splice($stackTrace, 0, InferredSpans::FRAMES_TO_SKIP); // skip inferred spans logic frames (or call backtrace in native)
            $apmFramesFilteredOut = $this->filterOutAPMFrames($stackTrace);

            $this->compareStackTraces($stackTrace, $this->lastStackTrace, $durationMs, $topFrameIsInternalFunction, $apmFramesFilteredOut);
        } catch (\Throwable $throwable) {
            self::logError($throwable->__toString());
        }
    }

    public function shutdown(): void
    {
        self::logDebug("shutdown");
        $this->shutdown = true;
        $fakeTrace = [];
        $this->compareStackTraces($fakeTrace, $this->lastStackTrace, 0, false, 0);
    }

    private function compareStackTraces(array &$stackTrace, array &$lastStackTrace, int $durationMs, bool $topFrameIsInternalFunction, ?int $apmFramesFilteredOut): void
    {
        $this->stackTraceId++;

        $identicalFramesCount = $this->getHowManyStackFramesAreIdenticalFromStackBottom($stackTrace, $lastStackTrace);
        self::logDebug("Same frames count: " . $identicalFramesCount); //, [$stackTrace, $lastStackTrace]);

        $lastStackTraceCount = count($lastStackTrace);

        // on previous stack trace - end all spans above identical frames
        $previousFrameStackTraceId = -1;
        $forceParentChangeFailed = false;

        for ($index = 0; $index < $lastStackTraceCount - $identicalFramesCount; $index++) {
            $endEpochNanos = null;
             // if last frame was internal function, so duraton contains it's time, previous ones ended between sampling interval - they're shorter
            if ($topFrameIsInternalFunction) {
                $endEpochNanos = $this->getStartTime($durationMs);
            }

            $dropSpan = false;

            if ($this->spanReductionEnabled) {
                $frameStackTraceId = $lastStackTrace[$index][self::METADATA_STACKTRACE_ID];

                $dropSpan = $previousFrameStackTraceId == $frameStackTraceId; // if frame came from same stackTrace (interval) - we're dropping all spans above as they have same timing

                $previousFrameStackTraceId = $frameStackTraceId;

                if (!$dropSpan) { // if span should not be dropped, search for spans with same traceId and get parent from last one
                    // find last span with same stackTraceId
                    $lastSpanParent = null;
                    for ($i = $index + 1; $i < $lastStackTraceCount - $identicalFramesCount; $i++) {
                        if ($lastStackTrace[$i][self::METADATA_STACKTRACE_ID] != $frameStackTraceId) {
                            break;
                        }
                        $lastSpanParent = $lastStackTrace[$i][self::METADATA_SPAN]->get()->getParentContext();
                    }

                    if ($lastSpanParent) {
                        self::logDebug(
                            "Changing parent of span. " . $lastStackTrace[$index][self::METADATA_SPAN]->get()->getName() . " new/old",
                            [$lastSpanParent, $lastStackTrace[$index][self::METADATA_SPAN]->get()->getParentContext()]
                        );
                        $forceParentChangeFailed = !force_set_object_propety_value($lastStackTrace[$index][self::METADATA_SPAN]->get(), "parentSpanContext", $lastSpanParent);
                    }
                }

                if ($forceParentChangeFailed && $dropSpan) {
                    $dropSpan = false;
                }
            }

            $this->endFrameSpan($lastStackTrace[$index], $dropSpan, $endEpochNanos);

            unset($lastStackTrace[$index]); // remove ended frame
        }

        // reindex array
        $lastStackTrace = array_values($lastStackTrace);

        $stackTraceCount = count($stackTrace);
        if ($stackTraceCount == $identicalFramesCount) {
            // no frames to start
            return;
        }

        $first = true;

        // start spans for all frames below identical frames

        for ($index = $stackTraceCount - $identicalFramesCount - 1; $index >= 0; $index--) {
            if ($first && $apmFramesFilteredOut && !empty($lastStackTrace)) {
                self::logDebug("Going to start span in previous span context");
                $this->startFrameSpan($stackTrace[$index], $durationMs, $lastStackTrace[0][self::METADATA_CONTEXT]->get(), $this->stackTraceId);
            } else {
                $this->startFrameSpan($stackTrace[$index], $durationMs, null, $this->stackTraceId);
            }

            $first = false;

            if ($this->attachStackTrace) {
                $stackTrace[$index][self::METADATA_SPAN]->get()->setAttribute(TraceAttributes::CODE_STACKTRACE, $this->getStackTrace($lastStackTrace));
            }

            if ($index == 0 && $topFrameIsInternalFunction) {
                $this->endFrameSpan($stackTrace[$index], false, null); // we don't need to save newest internal frame, it ended
            } else {
                array_unshift($lastStackTrace, $stackTrace[$index]); // push-copy frame in front of last stack trace for next interruption processing
            }
        }
    }

    private function getStartTime(int $durationMs): int
    {
        return Clock::getDefault()->now() - $durationMs * self::MILLIS_TO_NANOS;
    }

    private function filterOutAPMFrames(array &$stackTrace): ?int
    {
       // Filter out Elastic and Otel stacks
        $cutIndex = null;
        for ($index = count($stackTrace) - 1; $index >= 0; $index--) {
            $frame = $stackTrace[$index];
            if (
                isset($frame['class']) &&
                (str_starts_with($frame['class'], 'OpenTelemetry\\') ||
                str_starts_with($frame['class'], 'Elastic\\'))
            ) {
                $cutIndex = $index;
                break;
            }
        }

        if ($cutIndex !== null) {
            array_splice($stackTrace, 0, $cutIndex + 1);
        }
        return $cutIndex;
    }

    private function getHowManyStackFramesAreIdenticalFromStackBottom(array &$stackTrace, array &$lastStackTrace): int
    {
        // Helper function to check if two frames are identical
        $isSameFrame = function ($frame1, $frame2) {
            $keysToCompare = ['class', 'function', 'file', 'line', 'type'];
            foreach ($keysToCompare as $key) {
                if (($frame1[$key] ?? null) !== ($frame2[$key] ?? null)) {
                    return false;
                }
            }
            return true;
        };

        $stackTraceCount = count($stackTrace);
        $lastStackTraceCount = count($lastStackTrace);

        $count = min($stackTraceCount, $lastStackTraceCount);

        $index = 0;
        for ($index = 1; $index <= $count; $index++) {
            $stFrame = &$stackTrace[$stackTraceCount - $index];
            $lastStFrame = &$lastStackTrace[$lastStackTraceCount - $index];

            if ($isSameFrame($stFrame, $lastStFrame) == false) {
                return $index - 1;
            }
        }
        return $count;
    }

    private function getStackTrace($stackTrace): string
    {
        $str = "#0 {main}\n";
        $id = 1;
        foreach ($stackTrace as $frame) {
            if (array_key_exists('file', $frame)) {
                $file = $frame['file'] . '(' . $frame['line'] . ')';
            } else {
                $file = '[internal function]';
            }

            $str .= sprintf("#%d %s: %s%s%s\n", $id, $file, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']);
            $id++;
        }
        return $str;
    }

    private function startFrameSpan(array &$frame, int $durationMs, $parentContext, int $stackTraceId)
    {
        $parent = $parentContext ? $parentContext : Context::getCurrent();
        $builder = $this->tracer->spanBuilder($frame['function'])

            ->setParent($parent)
            ->setStartTimestamp($this->getStartTime($durationMs))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $frame['function'] ?? null)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $frame['file'] ?? null)
            ->setAttribute(TraceAttributes::CODE_LINENO, $frame['line'] ?? null)
            ->setAttribute('is_inferred', true);

        $span = $builder->startSpan();
        $context = $span->storeInContext($parent);
        $scope = Context::storage()->attach($context);

        $frame[self::METADATA_SPAN] = WeakReference::create($span);
        $frame[self::METADATA_CONTEXT] = WeakReference::create($context);
        $frame[self::METADATA_SCOPE] = WeakReference::create($scope);
        $frame[self::METADATA_STACKTRACE_ID] = $stackTraceId;

        self::logDebug("Span started: " . $span->getName() . " parentContext: " . ($parentContext ? "custom" : "default") . " stackTraceId: " . $stackTraceId);
    }

    private function endFrameSpan(array &$frame, bool $dropSpan, ?int $endEpochNanos = null)
    {
        if (!array_key_exists(self::METADATA_SPAN, $frame)) {
            self::logError("endFrameSpan missing metadata.", [$frame]);
            return;
        }

        if (!$dropSpan) {
            $dropSpan = $this->shouldDropTooShortSpan($frame, $endEpochNanos);
        }

        if ($dropSpan) {
            self::logDebug("Span dropped:   " . $frame[self::METADATA_SPAN]->get()->getName() . ' StackTraceId: ' . $frame[self::METADATA_STACKTRACE_ID]);
            $frame[self::METADATA_SCOPE]->get()->detach();
            unset($frame[self::METADATA_SCOPE]);
            unset($frame[self::METADATA_SPAN]);
            return;
        }

        $scope = Context::storage()->scope();
        $scope?->detach();

        if (!$scope || $scope->context() === Context::getCurrent()) {
            return;
        }

        $frame[self::METADATA_SPAN]->get()->end($endEpochNanos);
        self::logDebug("Span finished:  " . $frame[self::METADATA_SPAN]->get()->getName() . ' StackTraceId: ' . $frame[self::METADATA_STACKTRACE_ID]);

        unset($frame[self::METADATA_SPAN]);
    }

    private function shouldDropTooShortSpan(array &$frame, ?int $endEpochNanos = null): bool
    {
        if ($this->minSpanDuration <= 0) {
            return false;
        }

        $span = $frame[self::METADATA_SPAN]->get();

        /** @var int */
        $duration = 0;
        if ($endEpochNanos) {
            $duration = $endEpochNanos - $span->getStartEpochNanos();
        } else {
            $duration = $span->getDuration();
        }

        if ($duration < $this->minSpanDuration * self::MILLIS_TO_NANOS) {
            self::logDebug('Span ' . $frame[self::METADATA_SPAN]->get()->getName() . ' duration ' . intval($duration / self::MILLIS_TO_NANOS)
                . 'ms is too short to fit within the minimum span duration limit: ' . $this->minSpanDuration . 'ms');
            return true;
        }
        return false;
    }

}
