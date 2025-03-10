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

use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\FlagsUtil;
use ElasticOTelTests\Util\IterableUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlagsUtilTest extends TestCase
{
    /**
     * @return iterable<array{int, array<int, string>, list<string>}>
     */
    public static function dataProviderForTestConvertFlagsToHumanReadable(): iterable
    {
        /**
         * @return iterable<array{int, array<int, string>, list<string>}>
         */
        $genDataSets = function (): iterable {
            $hasIsRemoteMask = 256;
            $isRemoteMask = 512;
            $maskToName = [
                $hasIsRemoteMask => 'HAS_IS_REMOTE',
                $isRemoteMask    => 'IS_REMOTE',
            ];

            yield [0, $maskToName, []];
            yield [1, $maskToName, []];
            yield [$hasIsRemoteMask - 1, $maskToName, []];
            yield [$hasIsRemoteMask, $maskToName, ['HAS_IS_REMOTE']];
            yield [($hasIsRemoteMask | 1), $maskToName, ['HAS_IS_REMOTE']];
            yield [$isRemoteMask, $maskToName, ['IS_REMOTE']];
            yield [($isRemoteMask | 2), $maskToName, ['IS_REMOTE']];
            yield [($hasIsRemoteMask | $isRemoteMask), $maskToName, ['HAS_IS_REMOTE', 'IS_REMOTE']];
            yield [($hasIsRemoteMask | $isRemoteMask | 4), $maskToName, ['HAS_IS_REMOTE', 'IS_REMOTE']];
        };

        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($genDataSets);
    }

    /**
     * @param array<int, string> $maskToName
     * @param list<string>       $expectedResult
     */
    #[DataProvider('dataProviderForTestConvertFlagsToHumanReadable')]
    public function testExtractBitNames(int $flags, array $maskToName, array $expectedResult): void
    {
        $actualResult = IterableUtil::toList(FlagsUtil::extractBitNames($flags, $maskToName));
        self::assertSame($expectedResult, $actualResult);
    }
}
