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

namespace ElasticOTelTests\UnitTests\UtilTests;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\TestCaseBase;

final class ComponentTestsUtilUnitTest extends TestCaseBase
{
    /**
     * @return iterable<array{array<string, LogLevel>, array<array<string, LogLevel>>}>
     */
    public static function dataProviderForTestGenerateEscalatedLogLevels(): iterable
    {
        $prodCodeKey = ComponentTestCaseBase::LOG_LEVEL_FOR_PROD_CODE_KEY;
        $testCodeKey = ComponentTestCaseBase::LOG_LEVEL_FOR_TEST_CODE_KEY;
        $highestLevel = LogLevelUtil::getHighest();

        /**
         * When the initial already the highest
         */
        yield [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel], []];

        /**
         * When the initial one step below the highest
         */
        yield [
            // initialLevels:
            [$prodCodeKey => LogLevel::from($highestLevel->value - 1), $testCodeKey => $highestLevel],
            // expectedEscalatedLevelsSeq:
            [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel]],
        ];

        yield [
            // initialLevels:
            [$prodCodeKey => $highestLevel, $testCodeKey => LogLevel::from($highestLevel->value - 1)],
            // expectedEscalatedLevelsSeq:
            [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel]],
        ];

        /**
         * When the initial is the default
         */
        yield [
            // initialLevels:
            [$prodCodeKey => LogLevel::info, $testCodeKey => LogLevel::info],
            // expectedEscalatedLevelsSeq:
            [
                [$prodCodeKey => LogLevel::trace, $testCodeKey => LogLevel::trace],
                [$prodCodeKey => LogLevel::debug, $testCodeKey => LogLevel::trace],
                [$prodCodeKey => LogLevel::trace, $testCodeKey => LogLevel::debug],
                [$prodCodeKey => LogLevel::info, $testCodeKey => LogLevel::trace],
                [$prodCodeKey => LogLevel::trace, $testCodeKey => LogLevel::info],
                [$prodCodeKey => LogLevel::debug, $testCodeKey => LogLevel::debug],
                [$prodCodeKey => LogLevel::info, $testCodeKey => LogLevel::debug],
                [$prodCodeKey => LogLevel::debug, $testCodeKey => LogLevel::info],
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestGenerateEscalatedLogLevels
     *
     * @param array<string, LogLevel>        $initialLevels
     * @param array<array<string, LogLevel>> $expectedLevelsSeq
     *
     * @return void
     */
    public function testGenerateEscalatedLogLevels(array $initialLevels, array $expectedLevelsSeq): void
    {
        $dbgCtx = ['initialLevels' => $initialLevels, 'expectedLevelsSeq' => $expectedLevelsSeq];
        $actualEscalatedLevelsSeq = IterableUtil::toList(ComponentTestCaseBase::generateEscalatedLogLevels($initialLevels));
        $dbgCtx['actualEscalatedLevelsSeq'] = $actualEscalatedLevelsSeq;
        $i = 0;
        foreach ($actualEscalatedLevelsSeq as $actualLevels) {
            $dbgCtxPerIter = array_merge(['i' => $i, 'actualLevels' => $actualLevels], $dbgCtx);
            self::assertGreaterThan($i, count($expectedLevelsSeq), LoggableToString::convert($dbgCtxPerIter));
            $expectedLevels = $expectedLevelsSeq[$i];
            $dbgCtxPerIter['expectedLevels'] = $expectedLevels;
            self::assertCount(count($expectedLevels), $actualLevels, LoggableToString::convert($dbgCtxPerIter));
            foreach ($expectedLevels as $levelTypeKey => $expectedLevel) {
                $dbgCtxPerIter2 = array_merge(['levelTypeKey' => $levelTypeKey], $dbgCtxPerIter);
                $dbgCtxPerIter2Str = LoggableToString::convert($dbgCtxPerIter2);
                self::assertSame($expectedLevel, $actualLevels[$levelTypeKey], $dbgCtxPerIter2Str);
            }
            ++$i;
        }
        self::assertCount($i, $expectedLevelsSeq, LoggableToString::convert($dbgCtx));
    }
}
