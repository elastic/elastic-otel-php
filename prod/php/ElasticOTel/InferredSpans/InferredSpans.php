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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel\InferredSpans;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SemConv\Version;
use OpenTelemetry\Context\ContextInterface;
use Throwable;
use WeakReference;

/**
 * @phpstan-type StackTraceFrameCallType '->'|'::'
 * @phpstan-type ExtendedStackTraceFrame array{function: string, line?: int, file?: string, class?: class-string, type?: StackTraceFrameCallType, span: WeakReference<SpanInterface>,
 *  context: WeakReference<ContextInterface>, scope: WeakReference<ContextStorageScopeInterface>, stackTraceId: int}
 * @phpstan-type ExtendedStackTrace array<string|int, ExtendedStackTraceFrame>
 * @phpstan-type DebugBackTraceFrame array{function: string, line?: int, file?: string, class?: class-string, type?: StackTraceFrameCallType, args?: array<mixed>, object?: object}
 * @phpstan-type DebugBackTrace array<non-negative-int, DebugBackTraceFrame>
 */
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
    /** @var ExtendedStackTrace */
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

    // $durationMs - duration between interrupt request and interrupt occurrence
    public function captureStackTrace(int $durationMs, bool $topFrameIsInternalFunction): void
    {
        self::logDebug("captureStackTrace topFrameInternal: $topFrameIsInternalFunction, duration: $durationMs ms shutdown: " . $this->shutdown);

        if ($this->shutdown) {
            return;
        }

        try {
            /* @var DebugBackTrace $stackTrace */
            $stackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            array_splice($stackTrace, 0, InferredSpans::FRAMES_TO_SKIP); // skip PhpFacade/Inferred spans logic frames
            $apmFramesFilteredOutCount = $this->filterOutAPMFrames($stackTrace);

            $this->compareStackTraces($stackTrace, $durationMs, $topFrameIsInternalFunction, $apmFramesFilteredOutCount);
        } catch (Throwable $throwable) {
            self::logError($throwable->__toString());
        }
    }

    public function shutdown(): void
    {
        self::logDebug("shutdown");
        $this->shutdown = true;
        $fakeTrace = [];
        $this->compareStackTraces($fakeTrace, 0, false, 0);
    }

    /**
     * @param DebugBackTrace $stackTrace
    */
    private function compareStackTraces(array $stackTrace, int $durationMs, bool $topFrameIsInternalFunction, ?int $apmFramesFilteredOut): void
    {
        $this->stackTraceId++;

        $identicalFramesCount = $this->getHowManyStackFramesAreIdenticalFromStackBottom($stackTrace);
        self::logDebug("Same frames count: " . $identicalFramesCount); //, [$stackTrace, $this->lastStackTrace]);

        $lastStackTraceCount = count($this->lastStackTrace);
        $oldFramesCount = $lastStackTraceCount - $identicalFramesCount;

        // on previous stack trace - end all spans above identical frames
        $previousFrameStackTraceId = -1;
        $forceParentChangeFailed = false;

        for ($index = 0; $index < $oldFramesCount; $index++) {
            $endEpochNanos = null;
             // if last frame was internal function, so duration contains it's time, previous ones ended between sampling interval - they're shorter
            if ($topFrameIsInternalFunction) {
                $endEpochNanos = $this->getStartTime($durationMs);
            }

            $dropSpan = false;
            if ($this->spanReductionEnabled) {
                $dropSpan = $this->shouldReduceFrame($index, $oldFramesCount, $previousFrameStackTraceId, $forceParentChangeFailed);
            }

            $this->endFrameSpan($this->lastStackTrace[$index], $dropSpan, $endEpochNanos);

            unset($this->lastStackTrace[$index]); // remove ended frame
        }

        // reindex array
        $this->lastStackTrace = array_values($this->lastStackTrace);

        $stackTraceCount = count($stackTrace);
        if ($stackTraceCount == $identicalFramesCount) {
            // no frames to start
            return;
        }

        $first = true;

        // start spans for all frames below identical frames

        for ($index = $stackTraceCount - $identicalFramesCount - 1; $index >= 0; $index--) {
            if ($first && $apmFramesFilteredOut && !empty($this->lastStackTrace)) {
                self::logDebug("Going to start span in previous span context");
                $newFrame = $this->startFrameSpan($stackTrace[$index], $durationMs, $this->lastStackTrace[0][self::METADATA_CONTEXT]->get(), $this->stackTraceId);
            } else {
                $newFrame = $this->startFrameSpan($stackTrace[$index], $durationMs, null, $this->stackTraceId);
            }

            $first = false;

            if ($this->attachStackTrace) {
                $newFrame[self::METADATA_SPAN]->get()?->setAttribute(TraceAttributes::CODE_STACKTRACE, $this->getStackTrace($this->lastStackTrace));
            }

            if ($index == 0 && $topFrameIsInternalFunction) {
                /** @noinspection PhpRedundantOptionalArgumentInspection */
                $this->endFrameSpan($newFrame, false, null); // we don't need to save the newest internal frame, it ended
            } else {
                array_unshift($this->lastStackTrace, $newFrame); // push-copy frame in front of last stack trace for next interruption processing
            }
        }
    }

    private function getStartTime(int $durationMs): int
    {
        return Clock::getDefault()->now() - $durationMs * self::MILLIS_TO_NANOS;
    }

    /** @param-out DebugBackTrace $stackTrace
     *  @param DebugBackTrace $stackTrace
     *  @return ?int
     */
    private function filterOutAPMFrames(array &$stackTrace): ?int
    {
       // Filter out Elastic and Otel stack frames
        $cutIndex = null;
        for ($index = count($stackTrace) - 1; $index >= 0; $index--) {
            $frame = $stackTrace[$index];
            if (
                array_key_exists('class', $frame) &&
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

    /** @param DebugBackTrace $stackTrace */
    private function getHowManyStackFramesAreIdenticalFromStackBottom(array $stackTrace): int
    {
        /**
         * Helper function to check if two frames are identical
         *
         * @phpstan-param DebugBackTraceFrame $frame1
         * @phpstan-param DebugBackTraceFrame $frame2
         */
        $isSameFrame = function (array $frame1, array $frame2): bool {
            $keysToCompare = ['class', 'function', 'file', 'line', 'type'];
            foreach ($keysToCompare as $key) {
                if (($frame1[$key] ?? null) !== ($frame2[$key] ?? null)) {
                    return false;
                }
            }
            return true;
        };

        $stackTraceCount = count($stackTrace);
        $lastStackTraceCount = count($this->lastStackTrace);

        $count = min($stackTraceCount, $lastStackTraceCount);

        for ($index = 1; $index <= $count; $index++) {
            $stFrame = &$stackTrace[$stackTraceCount - $index];
            $lastStFrame = &$this->lastStackTrace[$lastStackTraceCount - $index];

            if (!$isSameFrame($stFrame, $lastStFrame)) {
                return $index - 1;
            }
        }
        return $count;
    }

    private function shouldReduceFrame(int $index, int $oldFramesCount, int &$previousFrameStackTraceId, bool &$forceParentChangeFailed): bool
    {
        $frameStackTraceId = $this->lastStackTrace[$index][self::METADATA_STACKTRACE_ID];

        $dropSpan = $previousFrameStackTraceId == $frameStackTraceId; // if frame came from same stackTrace (interval) - we're dropping all spans above as they have same timing

        $previousFrameStackTraceId = $frameStackTraceId;

        if (!$dropSpan) { // if span should not be dropped, search for spans with same traceId and get parent from last one
            // find last span with same stackTraceId
            $lastSpanParent = null;
            for ($i = $index + 1; $i < $oldFramesCount; $i++) {
                if ($this->lastStackTrace[$i][self::METADATA_STACKTRACE_ID] != $frameStackTraceId) {
                    break;
                }

                $span = $this->lastStackTrace[$i][self::METADATA_SPAN]->get();
                if (!$span instanceof Span) {
                    break;
                }

                $lastSpanParent = $span->getParentContext();
            }

            if ($lastSpanParent) {
                $span = $this->lastStackTrace[$index][self::METADATA_SPAN]->get();
                if (!$span instanceof Span) {
                    return false;
                }

                self::logDebug(
                    "Changing parent of span: '" . $span->getName() . "'",
                    ['new', $lastSpanParent, 'old', $span->getParentContext()]
                );

                $forceParentChangeFailed = !force_set_object_property_value($span, "parentSpanContext", $lastSpanParent);
            }
        }

        if ($forceParentChangeFailed && $dropSpan) {
            $dropSpan = false;
        }

        return $dropSpan;
    }

    /**
     * @phpstan-param ExtendedStackTraceFrame $frame
     */
    private function shouldDropTooShortSpan(array $frame, ?int $endEpochNanos = null): bool
    {
        if ($this->minSpanDuration <= 0) {
            return false;
        }

        $span = $frame[self::METADATA_SPAN]->get();
        if (!$span instanceof Span) {
            return false;
        }

        $duration = $endEpochNanos ? ($endEpochNanos - $span->getStartEpochNanos()) : $span->getDuration();
        if ($duration < $this->minSpanDuration * self::MILLIS_TO_NANOS) {
            self::logDebug('Span ' . $span->getName() . ' duration ' . intval($duration / self::MILLIS_TO_NANOS)
                . 'ms is too short to fit within the minimum span duration limit: ' . $this->minSpanDuration . 'ms');
            return true;
        }
        return false;
    }

    /**
     *  @param ExtendedStackTrace $stackTrace
     */
    private function getStackTrace(array $stackTrace): string
    {
        $str = "#0 {main}\n";
        $id = 1;
        foreach ($stackTrace as $frame) {
            if (array_key_exists('file', $frame)) {
                $file = $frame['file'] . '(' . ($frame['line'] ?? '') . ')';
            } else {
                $file = '[internal function]';
            }

            $str .= sprintf("#%d %s: %s%s%s\n", $id, $file, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']);
            $id++;
        }
        return $str;
    }

    /**
     * @phpstan-param DebugBackTraceFrame $frame
     *
     * @phpstan-return ExtendedStackTraceFrame
     */
    private function startFrameSpan(array $frame, int $durationMs, ?ContextInterface $parentContext, int $stackTraceId): array
    {
        $parent = $parentContext ?? Context::getCurrent();
        $builder = $this->tracer->spanBuilder(!empty($frame['function']) ? $frame['function'] : '[unknown]')
            ->setParent($parent)
            ->setStartTimestamp($this->getStartTime($durationMs))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, $frame['function'])
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $frame['file'] ?? null)
            ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $frame['line'] ?? null)
            ->setAttribute('is_inferred', true);

        $span = $builder->startSpan(); //OpenTelemetry\API\Trace\SpanInterface
        $context = $span->storeInContext($parent); //OpenTelemetry\Context\ContextInterface
        $scope = Context::storage()->attach($context); //OpenTelemetry\Context\ContextStorageScopeInterface

        $newFrame = $frame;
        $newFrame[self::METADATA_SPAN] = WeakReference::create($span);
        $newFrame[self::METADATA_CONTEXT] = WeakReference::create($context);
        $newFrame[self::METADATA_SCOPE] = WeakReference::create($scope);
        $newFrame[self::METADATA_STACKTRACE_ID] = $stackTraceId;

        self::logDebug("Span started: " . $newFrame['function'] . " parentContext: " . ($parentContext ? "custom" : "default") . " stackTraceId: " . $stackTraceId);
        return $newFrame;
    }

    /**
     * @phpstan-param ExtendedStackTraceFrame $frame
     */
    private function endFrameSpan(array $frame, bool $dropSpan, ?int $endEpochNanos = null): void
    {
        if (!array_key_exists(self::METADATA_SPAN, $frame)) { // @phpstan-ignore function.alreadyNarrowedType
            self::logError("endFrameSpan missing metadata.", [$frame]);
            return;
        }

        if (!$dropSpan) {
            $dropSpan = $this->shouldDropTooShortSpan($frame, $endEpochNanos);
        }

        $span = $frame[self::METADATA_SPAN]->get();
        if (!$span instanceof Span) {
            self::logDebug("Span in frame is not instanceof Trace\Span", [$span, $frame]);
            return;
        }

        if ($dropSpan) {
            self::logDebug("Span dropped:   " . $span->getName() . ' StackTraceId: ' . $frame[self::METADATA_STACKTRACE_ID]);
            $frame[self::METADATA_SCOPE]->get()?->detach();
            return;
        }

        $scope = Context::storage()->scope();
        $scope?->detach();

        if (!$scope || $scope->context() === Context::getCurrent()) {
            return;
        }

        $span->end($endEpochNanos);
        self::logDebug("Span finished:  " . $span->getName() . ' StackTraceId: ' . $frame[self::METADATA_STACKTRACE_ID]);
    }
}
