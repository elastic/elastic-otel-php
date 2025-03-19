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

use ElasticOTelTests\Util\AssertEx;
use PHPUnit\Framework\Assert;

final class StackTraceFrameExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /**
     * @phpstan-param LeafExpectations<?positive-int> $line
     */
    public function __construct(
        public readonly NullableStringExpectations $file,
        public readonly LeafExpectations $line,
        public readonly NullableStringExpectations $function,
    ) {
    }

    public static function matchAny(): self
    {
        /** @var ?self $cached */
        static $cached = null;
        return $cached ??= new self(NullableStringExpectations::matchAny(), LeafExpectations::matchAny(), NullableStringExpectations::matchAny()); // @phpstan-ignore argument.type
    }

    private static function trimParsedConvertedToStringFunction(string $function): string
    {
        /** @var non-empty-string $expectedSuffix */
        static $expectedSuffix = '()';
        Assert::assertStringEndsWith($expectedSuffix, $function);
        return substr($function, 0, strlen($function) - strlen($expectedSuffix));
    }

    /**
     * @param-out ?string $file
     * @param-out ?positive-int $line
     * @param-out ?string $function
     */
    private static function parseFrameConvertedToString( // @phpstan-ignore paramOut.unusedType
        string $convertedToString,
        ?string &$file /* <- out */,
        ?int &$line /* <- out */,
        ?string &$function /* <- out */,
    ): void {
        // #0 [internal function]: ElasticOTelTests\\ComponentTests\\InferredSpansComponentTest::appCodeForTestInferredSpans()
        // #1 /app/AppCodeHostBase.php(112): call_user_func()
        // #2 /app/CliScriptAppCodeHost.php(35): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase->callAppCode()
        // #3 /app/AppCodeHostBase.php(83): ElasticOTelTests\\ComponentTests\\Util\\CliScriptAppCodeHost->runImpl()
        // #4 /app/SpawnedProcessBase.php(107): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::{closure:ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::run():68}()
        // #5 /app/AppCodeHostBase.php(67): ElasticOTelTests\\ComponentTests\\Util\\SpawnedProcessBase::runSkeleton()
        // #6 /app/runCliScriptAppCodeHost.php(28): ElasticOTelTests\\ComponentTests\\Util\\AppCodeHostBase::run()
        //    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
        //    i.e., input string has index prefix already cut off

        /** @var non-empty-string $afterFileLineSep */
        static $afterFileLineSep = ': ';
        $afterFileLinePos = strpos($convertedToString, $afterFileLineSep);
        if ($afterFileLinePos === false) {
            $file = null;
            $line = null;
            $function = self::trimParsedConvertedToStringFunction($convertedToString);
            return;
        }

        $function = self::trimParsedConvertedToStringFunction(substr($convertedToString, $afterFileLinePos + strlen($afterFileLineSep)));
        $fileLinePart = substr($convertedToString, 0, $afterFileLinePos);
        $openParenPos = strpos($fileLinePart, '(');
        if ($openParenPos === false) {
            $file = $fileLinePart;
            if ($file === '[internal function]') {
                $file = null;
            }
            $line = null;
            return;
        }
        $file = substr($fileLinePart, 0, $openParenPos);
        $lineSuffix = substr($fileLinePart, $openParenPos + 1);
        $closeParenPos = strpos($lineSuffix, ')');
        Assert::assertIsInt($closeParenPos);
        $line = AssertEx::isPositiveInt(AssertEx::stringIsInt(substr($lineSuffix, 0, $closeParenPos)));
    }

    public function assertMatchesConvertedToString(string $convertedToString): void
    {
        self::parseFrameConvertedToString($convertedToString, /* out */ $file, /* out */ $line, /* out */ $function);
        $this->file->assertMatches($file);
        $this->line->assertMatches($line);
        $this->function->assertMatches($function);
    }

    public function assertMatchesMixed(mixed $actual): void
    {
        $this->assertMatchesConvertedToString(AssertEx::isString($actual));
    }
}
