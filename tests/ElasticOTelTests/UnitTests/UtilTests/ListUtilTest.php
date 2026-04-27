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

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DisableDebugContextTestTrait;
use ElasticOTelTests\Util\ListUtilForTests;
use ElasticOTelTests\Util\TestCaseBase;

final class ListUtilTest extends TestCaseBase
{
    use DisableDebugContextTestTrait;

    public static function testBringToFront(): void
    {
        /**
         * @template T
         *
         * @param T $val
         * @param non-empty-list<T> $list
         * @param non-empty-list<T> $expectedResult
         */
        $impl = function (mixed $val, array $list, array $expectedResult): void {
            /** @var non-empty-list<mixed> $list */
            $actualResult = ListUtilForTests::bringToFront($val, $list);
            AssertEx::equalLists($expectedResult, $actualResult);
        };

        $impl(1, [1], [1]);

        $impl('123', ['123', 123], ['123', 123]);
        $impl(123, ['123', 123], [123, '123']);

        $impl('a', ['a', 2, true], ['a', 2, true]);
        $impl(2, ['a', 2, true], [2, 'a', true]);
        $impl(true, ['a', 2, true], [true, 'a', 2]);
    }
}
