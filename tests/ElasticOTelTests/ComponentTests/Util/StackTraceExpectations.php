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

namespace ElasticOTelTests\ComponentTests\Util;

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\StackTraceUtil;
use ElasticOTelTests\Util\TextUtilForTests;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-import-type DebugBacktraceResult from StackTraceUtil
 */
final class StackTraceExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /**
     * @param list<StackTraceFrameExpectations> $frames
     */
    public function __construct(
        public readonly array $frames,
        public readonly bool $allowToBePrefixOfActual,
    ) {
    }

    public static function matchAny(): self
    {
        /** @var ?self $cached */
        static $cached = null;
        return $cached ??= new self(frames: [], allowToBePrefixOfActual: true);
    }

    /**
     * @phpstan-param DebugBacktraceResult $debugBacktraceResult
     */
    public static function fromDebugBacktrace(array $debugBacktraceResult): self
    {
        /** @var list<StackTraceFrameExpectations> $framesExpectations */
        $framesExpectations = [];
        foreach ($debugBacktraceResult as $debugBacktraceFrame) {
            $frameExpectationsBuilder = new StackTraceFrameExpectationsBuilder();
            if (ArrayUtil::getValueIfKeyExists(StackTraceUtil::FILE_KEY, $debugBacktraceFrame, /* out */ $file)) {
                $frameExpectationsBuilder->file(AssertEx::isString($file));
            }
            if (ArrayUtil::getValueIfKeyExists(StackTraceUtil::LINE_KEY, $debugBacktraceFrame, /* out */ $line)) {
                $frameExpectationsBuilder->line(AssertEx::isPositiveInt($line));
            }
            $class = AssertEx::isNullableString(ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::CLASS_KEY, $debugBacktraceFrame, null));
            $methodKind = AssertEx::isNullableString(ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::METHOD_KIND_KEY, $debugBacktraceFrame, null));
            $func = AssertEx::isNullableString(ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::FUNCTION_KEY, $debugBacktraceFrame, null));
            $combinedFunc = ($class ?? '') . ($methodKind ?? '') . ($func ?? '');
            $frameExpectationsBuilder->function($combinedFunc);
            $framesExpectations[] = $frameExpectationsBuilder->build();
        }
        return new self($framesExpectations, allowToBePrefixOfActual: false);
    }

    public function assertMatchesConvertedToString(string $convertedToString): void
    {
        // #0 [internal function]: ElasticOTelTests\\ComponentTests\\InferredSpansComponentTest::appCodeForTestInferredSpans
        // #1 /app/AppCodeHostBase.php(112): call_user_func
        // #2 /app/CliScriptAppCodeHost.php(35): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase->callAppCode
        // #3 /app/AppCodeHostBase.php(83): ElasticOTelTests\\ComponentTests\\Util\\CliScriptAppCodeHost->runImpl
        // #4 /app/SpawnedProcessBase.php(107): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::{closure:ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::run():68}
        // #5 /app/AppCodeHostBase.php(67): ElasticOTelTests\\ComponentTests\\Util\\SpawnedProcessBase::runSkeleton
        // #6 /app/runCliScriptAppCodeHost.php(28): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::run
        // #7 {main}

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $index = 0;
        $encounteredMain = false;
        $dbgCtx->pushSubScope();
        foreach (TextUtilForTests::iterateLines($convertedToString, keepEndOfLine: false) as $textLine) {
            $dbgCtx->resetTopSubScope(compact('index', 'textLine'));

            if (TextUtil::isEmptyString($textLine)) {
                continue;
            }

            // Line with "{main}" should be the last non-empty line
            Assert::assertFalse($encounteredMain);
            $expectedIndexPrefix = "#{$index} ";
            Assert::assertStringStartsWith($expectedIndexPrefix, $textLine);
            $frameConvertedToString = substr($textLine, strlen($expectedIndexPrefix));
            if ($frameConvertedToString === '{main}') {
                $encounteredMain = true;
                continue;
            }
            $frameExpectations = $index < count($this->frames) ? $this->frames[$index] : StackTraceFrameExpectations::matchAny();
            $frameExpectations->assertMatchesConvertedToString($frameConvertedToString);
            ++$index;
        }
        $dbgCtx->popSubScope();

        if ($this->allowToBePrefixOfActual) {
            Assert::assertGreaterThanOrEqual(count($this->frames), $index);
        } else {
            Assert::assertSame(count($this->frames), $index);
        }
    }

    public function assertMatchesMixed(mixed $actual): void
    {
        $this->assertMatchesConvertedToString(AssertEx::isString($actual));
    }
}
