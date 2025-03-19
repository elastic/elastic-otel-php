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

namespace ElasticOTelTests\Util;

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-import-type Context from DebugContext
 * @phpstan-import-type StackTraceSegment from DebugContext
 */
final class DebugContextScope implements LoggableInterface
{
    /** @var Context[] */
    private array $lowerSubScopesContexts;

    /** @var Context */
    private array $topSubScopeContext;

    /**
     * @param StackTraceSegment $stackTraceSegment
     * @param Context           $initialCtx
     */
    public function __construct(
        private readonly ListSlice $stackTraceSegment,
        array $initialCtx
    ) {
        Assert::assertNotEmpty($stackTraceSegment);
        $this->topSubScopeContext = $initialCtx;
        $this->lowerSubScopesContexts = [];
    }

    /**
     * @param Context  $from
     * @param Context &$to
     *
     * @param-out Context $to
     */
    public static function appendContext(array $from, /* in,out */ array &$to): void
    {
        // Remove keys that exist in new context to make the new entry the last in added order
        ArrayUtilForTests::removeByKeys(/* in,out */ $to, IterableUtil::keys($from));
        ArrayUtilForTests::append(from: $from, to: $to);
    }

    /**
     * @param Context $ctx
     */
    public function add(array $ctx): void
    {
        self::appendContext(from: $ctx, to: $this->topSubScopeContext);
    }

    public function pushSubScope(): void
    {
        $this->lowerSubScopesContexts[] = $this->topSubScopeContext;
        $this->topSubScopeContext = [];
    }

    /**
     * @phpstan-param Context $ctx
     */
    public function resetTopSubScope(array $ctx): void
    {
        $this->topSubScopeContext = $ctx;
    }

    public function popSubScope(): void
    {
        Assert::assertNotEmpty($this->lowerSubScopesContexts);
        $this->topSubScopeContext = AssertEx::notNull(array_pop($this->lowerSubScopesContexts));
    }

    /**
     * @param StackTraceSegment $currentStackTraceTopSegment
     *
     * @param-out non-negative-int $matchingFrameIndex
     * @param-out bool $matchingFrameHasSameLine
     *
     * @phpstan-assert-if-true non-negative-int $matchingFrameIndex
     * @phpstan-assert-if-true bool $matchingFrameHasSameLine
     */
    public function syncWithCallStack(ListSlice $currentStackTraceTopSegment, /* out */ ?int &$matchingFrameIndex, /* out */ ?bool &$matchingFrameHasSameLine): bool
    {
        $thisStackTraceSegmentCount = count($this->stackTraceSegment);
        Assert::assertGreaterThan(0, $thisStackTraceSegmentCount);
        if ($currentStackTraceTopSegment->count() < $thisStackTraceSegmentCount) {
            return false;
        }

        $currentCallStackTraceSubSegment = IterableUtil::takeUpTo($currentStackTraceTopSegment, $thisStackTraceSegmentCount);
        /** @var int $frameIndex */
        /** @var ClassicFormatStackTraceFrame $thisStackTraceFrame */
        /** @var ClassicFormatStackTraceFrame $currentStackTraceFrame */
        foreach (IterableUtil::zipWithIndex($this->stackTraceSegment, $currentCallStackTraceSubSegment) as [$frameIndex, $thisStackTraceFrame, $currentStackTraceFrame]) {
            if (!$thisStackTraceFrame->canBeSameCall($currentStackTraceFrame)) {
                return false;
            }
            Assert::assertLessThan($thisStackTraceSegmentCount, $frameIndex);
            // If source code line is different that means that all the scopes up to top of the scopes stack
            // are for calls different from the ones on the current calls stack trace
            if (($frameIndex !== ($thisStackTraceSegmentCount - 1)) && ($thisStackTraceFrame->line !== $currentStackTraceFrame->line)) {
                return false;
            }
        }

        // $this->stackTraceSegment should not be empty so foreach loop above should iterate at least once
        // so $thisStackTraceFrame and $currentStackTraceFrame should be defined
        $matchingFrameHasSameLine = ($thisStackTraceFrame->line === $currentStackTraceFrame->line); // @phpstan-ignore variable.undefined, variable.undefined
        $thisStackTraceFrame->line = $currentStackTraceFrame->line; // @phpstan-ignore variable.undefined, variable.undefined
        $matchingFrameIndex = $thisStackTraceSegmentCount - 1; // @phpstan-ignore paramOut.type
        return true;
    }

    /**
     * @return Context
     */
    public function getContext(): array
    {
        $result = [];
        foreach ($this->lowerSubScopesContexts as $subScopeCtx) {
            self::appendContext(from: $subScopeCtx, to: $result);
        }
        self::appendContext(from: $this->topSubScopeContext, to: $result);
        return $result;
    }

    public function getName(): string
    {
        $topStackFrame = $this->stackTraceSegment->getLastValue();
        $classMethodPart = '';
        if ($topStackFrame->class !== null) {
            $classMethodPart .= $topStackFrame->class;
        }
        if ($topStackFrame->function !== null) {
            if ($classMethodPart !== '') {
                $classMethodPart .= '::';
            }
            $classMethodPart .= $topStackFrame->function;
        }

        $fileLinePart = '';
        if ($topStackFrame->file !== null) {
            $fileLinePart .= $topStackFrame->file;
            if ($topStackFrame->line !== null) {
                $fileLinePart .= ':' . $topStackFrame->line;
            }
        }

        if ($classMethodPart === '') {
            return $fileLinePart;
        }

        return $classMethodPart . ' [' . $fileLinePart . ']';
    }

    /**
     * @param Context $initialCtx
     */
    public function reset(array $initialCtx): void
    {
        $this->topSubScopeContext = $initialCtx;
        $this->lowerSubScopesContexts = [];
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'stackTraceSegment' => $this->stackTraceSegment,
                'lower sub scopes count' => count($this->lowerSubScopesContexts),
                'top sub scope count' => count($this->topSubScopeContext)
            ]
        );
    }
}
