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
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SemConv\Version;
use WeakReference;

class InferredSpans
{
    use LogsMessagesTrait;

    private const FRAMES_TO_SKIP = 2;
    private $tracer;
    private ?array $lastStackTrace;

    public function __construct()
    {
        $this->tracer = Globals::tracerProvider()->getTracer(
            'co.elastic.php.elastic-inferred-spans',
            null,
            Version::VERSION_1_25_0->url(),
        );
        $this->lastStackTrace = array();
    }

    private function getStartTime(int $durationMs)
    {
        return Clock::getDefault()->now() - $durationMs * 1000000;
    }

    //TODO general question - add interval as duration? or half of interval? or don't add frame only if on second frame?
    //TODO or add if first is internal, then we know that it is at least $duration long

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


    // $durationMs - duration between interrupt request and interrupt occurence
    public function captureStackTrace(int $durationMs, bool $topFrameIsInternalFunction)
    {
        self::logDebug("captureStackTrace topFrameInternal: $topFrameIsInternalFunction, duration: $durationMs ms");

        try {
            $stackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); //TODO should I ask for object and compare it in frames? probably

            array_splice($stackTrace, 0, InferredSpans::FRAMES_TO_SKIP); // skip inferred spans logic frames (or call backtrace in native)
            $apmFramesFilteredOut = $this->filterOutAPMFrames($stackTrace);

            // if (count($stackTrace) == 0) {
            //     if ($this->lastStackTrace != null) {
            //         foreach ($this->lastStackTrace as $index => &$frame) {
            //             $this->endFrameSpan($frame);
            //         }
            //     }
            //     $this->lastStackTrace = array();
            //     return;
            // }

            $this->compareStackTraces($stackTrace, $this->lastStackTrace, $durationMs, $topFrameIsInternalFunction, $apmFramesFilteredOut);
        } catch (\Throwable $throwable) {
            self::logError($throwable->__toString());
        }
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

    private function compareStackTraces(array &$stackTrace, array &$lastStackTrace, int $durationMs, bool $topFrameIsInternalFunction, $apmFramesFilteredOut)
    {
        $identicalFramesCount = $this->getHowManyStackFramesAreIdenticalFromStackBottom($stackTrace, $lastStackTrace);
        self::logDebug("Same frames count: " . $identicalFramesCount); //[$stackTrace, $lastStackTrace]

        $lastStackTraceCount = count($lastStackTrace);

        // on previous stack trace - end all spans above identical frames
        for ($index = 0; $index < $lastStackTraceCount - $identicalFramesCount; $index++) {
            $endEpochNanos = null;
             // if last frame was internal function, so duraton contains it's time, previous ones ended between sampling interval - they're much shorter
            if ($topFrameIsInternalFunction) {
                $endEpochNanos = $this->getStartTime($durationMs);
            }
            $this->endFrameSpan($lastStackTrace[$index], $endEpochNanos);
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
            if ($first && $apmFramesFilteredOut && $lastStackTrace[0] ?? null) {
                echo "PAPLO ***************************** STARTING SPAN FROM PREVIOUS SPAN CONTEXT\n";

                $this->startFrameSpan($stackTrace[$index], $durationMs, $lastStackTrace[0]['context']->get());
            } else {
                $this->startFrameSpan($stackTrace[$index], $durationMs, null);
            }

            $first = false;

            if ($index == 0 && $topFrameIsInternalFunction) {
                $this->endFrameSpan($stackTrace[$index]); // we don't need to save newest internal frame, it ended
            } else {
                array_unshift($lastStackTrace, $stackTrace[$index]); // push-copy frame in front of last stack trace for next interruption processing
            }
        }
    }

    private function startFrameSpan(array &$frame, int $durationMs, $parentContext)
    {
        $parent = $parentContext ? $parentContext : Context::getCurrent();
        $builder = $this->tracer->spanBuilder($frame['function'])
        ->setParent($parent)
        ->setStartTimestamp($this->getStartTime($durationMs))
        ->setSpanKind(SpanKind::KIND_INTERNAL)
        ->setAttribute(TraceAttributes::CODE_FUNCTION, $frame['function'] ?? null)
        ->setAttribute(TraceAttributes::CODE_FILEPATH, $frame['file'] ?? null)
        ->setAttribute(TraceAttributes::CODE_LINENO, $frame['line'] ?? null)
        ->setAttribute('is_inferred', true); // also in java: LINK_IS_CHILD bool 'is_child',  code.stacktrace - attribute key


        $span = $builder->startSpan();
        $context = $span->storeInContext($parent);
        $scope = Context::storage()->attach($context);

        $frame['span'] = WeakReference::create($span);
        $frame['context'] = WeakReference::create($context);
        $frame['scope'] = WeakReference::create($scope);

        echo "PAPLO ***************************** SPAN STARTED: " . $span->getName() . " PARENT CONTEXT: " . ($parentContext ? "PODANY" : "NIE PODANY")  . "\n";
    }

    private function endFrameSpan(array &$frame, ?int $endEpochNanos = null)
    {
        if (!array_key_exists('span', $frame)) {
            self::logError("endFrameSpan missing metadata.", [$frame]);
            return;
        }

        $scope = Context::storage()->scope();

        if ($frame['scope']->get() != $scope) {
            echo "PAPLO ***************************** SCOPE DIFFERENT\n";
        }

        $scope?->detach();

        if (!$scope || $scope->context() === Context::getCurrent()) {
            echo "PAPLO ***************************** SPAN END MISSING SPAN CONTEXT\n";
            return;
        }


        $frame['span']->get()->end($endEpochNanos);
        echo "PAPLO ***************************** SPAN END: " . $frame['span']->get()->getName()  . "\n";

        unset($frame['span']);
    }
}
