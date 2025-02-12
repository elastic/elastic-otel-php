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
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DisableDebugContextTestTrait;
use ElasticOTelTests\Util\ListSlice;
use ElasticOTelTests\Util\TestCaseBase;
use OutOfBoundsException;

/**
 * @phpstan-type TestConstructorInput array{'base': array<mixed>, 'offset'?: non-negative-int, 'length'?: ?non-negative-int}
 * @phpstan-type TestConstructorArgs array{'input': TestConstructorInput, 'expectedOutput': iterable<mixed>}
 */
final class ListSliceTest extends TestCaseBase
{
    use DisableDebugContextTestTrait;

    /**
     * @return iterable<array{iterable<mixed>, iterable<mixed>}>
     */
    public static function dataProviderForTestConstructorValidInput(): iterable
    {
        /**
         * @return iterable<TestConstructorArgs>
         */
        $genDataSets = function (): iterable {
            yield ['input' => ['base' => [], 'offset' => 0], 'expectedOutput' => []];

            yield ['input' => ['base' => [], 'offset' => 0, 0], 'expectedOutput' => []];

            yield ['input' => ['base' => ['a'], 'offset' => 1], 'expectedOutput' => []];

            yield ['input' => ['base' => ['a'], 'offset' => 0], 'expectedOutput' => ['a']];
            yield ['input' => ['base' => ['a', 'b'], 'offset' => 0], 'expectedOutput' => ['a', 'b']];
            yield ['input' => ['base' => ['a', 'b'], 'offset' => 1], 'expectedOutput' => ['b']];

            yield ['input' => ['base' => ['a', 'b', 'c'],'offset' => 0], 'expectedOutput' => ['a', 'b', 'c']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 0, 'length' => 0], 'expectedOutput' => []];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 0, 'length' => 1], 'expectedOutput' => ['a']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 0, 'length' => 2], 'expectedOutput' => ['a', 'b']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 0, 'length' => 3], 'expectedOutput' => ['a', 'b', 'c']];

            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 1], 'expectedOutput' => ['b', 'c']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 1, 'length' => 0], 'expectedOutput' => []];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 1, 'length' => 1], 'expectedOutput' => ['b']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 1, 'length' => 2], 'expectedOutput' => ['b', 'c']];

            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 2], 'expectedOutput' => ['c']];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 2, 'length' => 0], 'expectedOutput' => []];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 2, 'length' => 1], 'expectedOutput' => ['c']];

            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 3], 'expectedOutput' => []];
            yield ['input' => ['base' => ['a', 'b', 'c'], 'offset' => 3, 'length' => 0], 'expectedOutput' => []];
        };

        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($genDataSets);
    }

    /**
     * @dataProvider dataProviderForTestConstructorValidInput
     *
     * @param TestConstructorInput $input
     * @param iterable<mixed>      $expectedOutput
     */
    public static function testConstructorValidInput(array $input, iterable $expectedOutput): void
    {
        if (array_key_exists('offset', $input)) {
            if (array_key_exists('length', $input)) {
                $actualOutput = new ListSlice($input['base'], $input['offset'], $input['length']);
            } else {
                $actualOutput = new ListSlice($input['base'], $input['offset']);
            }
        } else {
            $actualOutput = new ListSlice($input['base']);
        }
        AssertEx::sameValuesListIterables($expectedOutput, $actualOutput);
    }

    /**
     * @return iterable<array{callable(): mixed}>
     */
    public static function dataProviderForTestInvalidInput(): iterable
    {
        yield [fn() => new ListSlice([], 1)];
        yield [fn() => new ListSlice(['a'], 2)];
        yield [fn() => new ListSlice(['a', 'b'], 3)];
        yield [fn() => new ListSlice(['a', 'b', 'b'], 4)];

        yield [fn() => new ListSlice([], 0, 1)];
        yield [fn() => new ListSlice(['a'], 1, 1)];
        yield [fn() => new ListSlice(['a', 'b'], 2, 1)];
        yield [fn() => new ListSlice(['a', 'b', 'b'], 1, 3)];
        yield [fn() => new ListSlice(['a', 'b', 'b'], 2, 2)];
        yield [fn() => new ListSlice(['a', 'b', 'b'], 3, 1)];
    }

    /**
     * @dataProvider dataProviderForTestInvalidInput
     *
     * @param callable(): mixed $throwingCall
     */
    public static function testInvalidInput(callable $throwingCall): void
    {
        AssertEx::throws(OutOfBoundsException::class, $throwingCall);
    }

    public static function testWithoutPrefix(): void
    {
        AssertEx::sameValuesListIterables([], (new ListSlice([]))->withoutPrefix(0));
        AssertEx::throws(OutOfBoundsException::class, fn() => (new ListSlice([]))->withoutPrefix(1));

        AssertEx::sameValuesListIterables(['a'], (new ListSlice(['a']))->withoutPrefix(0));
        AssertEx::sameValuesListIterables([], (new ListSlice(['a']))->withoutPrefix(1));

        AssertEx::sameValuesListIterables(['a', 'b', 'c'], (new ListSlice(['a', 'b', 'c']))->withoutPrefix(0));
        AssertEx::sameValuesListIterables(['b', 'c'], (new ListSlice(['a', 'b', 'c']))->withoutPrefix(1));
        AssertEx::sameValuesListIterables(['c'], (new ListSlice(['a', 'b', 'c']))->withoutPrefix(2));
        AssertEx::sameValuesListIterables([], (new ListSlice(['a', 'b', 'c']))->withoutPrefix(3));
    }

    public static function testWithoutSuffix(): void
    {
        AssertEx::sameValuesListIterables([], (new ListSlice([]))->withoutSuffix(0));
        AssertEx::throws(OutOfBoundsException::class, fn() => (new ListSlice([]))->withoutSuffix(1));

        AssertEx::sameValuesListIterables(['a'], (new ListSlice(['a']))->withoutSuffix(0));
        AssertEx::sameValuesListIterables([], (new ListSlice(['a']))->withoutSuffix(1));

        AssertEx::sameValuesListIterables(['a', 'b', 'c'], (new ListSlice(['a', 'b', 'c']))->withoutSuffix(0));
        AssertEx::sameValuesListIterables(['a', 'b'], (new ListSlice(['a', 'b', 'c']))->withoutSuffix(1));
        AssertEx::sameValuesListIterables(['a'], (new ListSlice(['a', 'b', 'c']))->withoutSuffix(2));
        AssertEx::sameValuesListIterables([], (new ListSlice(['a', 'b', 'c']))->withoutSuffix(3));
    }
}
