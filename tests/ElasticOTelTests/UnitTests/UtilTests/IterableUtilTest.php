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
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\TestCaseBase;
use Generator;

/**
 * @phpstan-type TestTakeUpToInput array{'iterable': iterable<mixed>, 'upTo': non-negative-int}
 * @phpstan-type TestTakeUpToArgs array{'input': TestTakeUpToInput, 'expectedOutput': array<mixed>}
 */
final class IterableUtilTest extends TestCaseBase
{
    public static function testPrepend(): void
    {
        AssertEx::equalLists([1, 2], IterableUtil::toList(IterableUtil::prepend(1, [2])));
        AssertEx::equalLists([1, 2, 3], IterableUtil::toList(IterableUtil::prepend(1, [2, 3])));
        AssertEx::equalLists([1], IterableUtil::toList(IterableUtil::prepend(1, [])));
    }

    public static function testArraySuffix(): void
    {
        AssertEx::equalLists([1, 2], IterableUtil::toList(IterableUtil::arraySuffix([1, 2], 0)));
        AssertEx::equalLists([2], IterableUtil::toList(IterableUtil::arraySuffix([1, 2], 1)));
        AssertEx::equalLists([], IterableUtil::toList(IterableUtil::arraySuffix([1, 2], 2)));
        AssertEx::equalLists([], IterableUtil::toList(IterableUtil::arraySuffix([1, 2], 3)));
        AssertEx::equalLists([], IterableUtil::toList(IterableUtil::arraySuffix([], 0)));
        AssertEx::equalLists([], IterableUtil::toList(IterableUtil::arraySuffix([], 1)));
    }

    /**
     * @return iterable<array{mixed[][], mixed[][]}>
     */
    public static function dataProviderForTestZip(): iterable
    {
        yield [[[]], []];
        yield [[[], []], []];
        yield [[[], [], []], []];

        yield [[['a'], [1]], [['a', 1]]];
        yield [[['a', 'b'], [1, 2]], [['a', 1], ['b', 2]]];
        yield [[['a', 'b', 'c'], [1, 2, 3], [4.4, 5.5, 6.6]], [['a', 1, 4.4], ['b', 2, 5.5], ['c', 3, 6.6]]];
    }

    /**
     * @param iterable<mixed>[] $inputIterables
     * @param mixed[][]         $expectedOutput
     */
    private static function helperForTestZip(bool $withIndex, array $inputIterables, array $expectedOutput): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $dbgCtx->add(['count($inputIterables)' => count($inputIterables)]);
        $i = 0;
        foreach (($withIndex ? IterableUtil::zipWithIndex(...$inputIterables) : IterableUtil::zip(...$inputIterables)) as $actualTuple) {
            $dbgCtx->add(compact('i', 'actualTuple'));
            self::assertLessThan(count($expectedOutput), $i);
            $expectedTuple = $expectedOutput[$i];
            AssertEx::equalLists($expectedTuple, $actualTuple);
            ++$i;
        }
        self::assertSame(count($expectedOutput), $i);
    }

    /**
     * @dataProvider dataProviderForTestZip
     *
     * @param mixed[][] $inputArrays
     * @param mixed[][] $expectedOutput
     */
    public static function testZip(array $inputArrays, array $expectedOutput): void
    {
        self::helperForTestZip(/* withIndex */ false, $inputArrays, $expectedOutput);

        /**
         * @param mixed[] $inputArray
         *
         * @return Generator<mixed>
         */
        $arrayToGenerator = function (array $inputArray): Generator {
            foreach ($inputArray as $val) {
                yield $val;
            }
        };

        self::helperForTestZip(/* withIndex */ false, array_map($arrayToGenerator(...), $inputArrays), $expectedOutput);
    }

    /**
     * @dataProvider dataProviderForTestZip
     *
     * @param mixed[][] $inputArrays
     * @param mixed[][] $expectedOutputWithoutIndex
     */
    public static function testZipWithIndex(array $inputArrays, array $expectedOutputWithoutIndex): void
    {
        $expectedOutput = [];
        $index = 0;
        foreach ($expectedOutputWithoutIndex as $expectedTupleWithoutIndex) {
            $expectedOutput[] = [$index++, ...$expectedTupleWithoutIndex];
        }

        self::helperForTestZip(/* withIndex */ true, $inputArrays, $expectedOutput);

        /**
         * @param mixed[] $inputArray
         *
         * @return Generator<mixed>
         */
        $arrayToGenerator = function (array $inputArray): Generator {
            foreach ($inputArray as $val) {
                yield $val;
            }
        };

        self::helperForTestZip(/* withIndex */ true, array_map($arrayToGenerator(...), $inputArrays), $expectedOutput);
    }

    public static function testMap(): void
    {
        /**
         * @template TInputValue
         * @template TOutputValue
         *
         * @param iterable<TInputValue>               $inputRange
         * @param callable(TInputValue): TOutputValue $mapFunc
         * @param iterable<TOutputValue>              $expectedOutputRange
         */
        $impl = function (iterable $inputRange, callable $mapFunc, iterable $expectedOutputRange): void {
            $actualOutputRange = IterableUtil::map($inputRange, $mapFunc);
            foreach (IterableUtil::zip($expectedOutputRange, $actualOutputRange) as $expectedActualOutputValues) {
                self::assertCount(2, $expectedActualOutputValues);
                self::assertSame($expectedActualOutputValues[0], $expectedActualOutputValues[1]);
            }
        };

        /**
         * @template T of number
         *
         * @param T $x
         *
         * @return T
         *
         * @noinspection PhpDocSignatureInspection
         */
        $x2Func = fn (int|float $x) => $x * 2;

        $impl([], $x2Func, []);
        $impl([1], $x2Func, [2]);
        $impl([1, 2], $x2Func, [2, 4]);
        $impl([1.2, 3, 4.6], $x2Func, [2.4, 6, 9.2]);
    }

    public static function testMax(): void
    {
        /**
         * @template T of number
         * @param iterable<T> $range
         * @param T           $expectedResult
         */
        $impl = function (iterable $range, mixed $expectedResult): void {
            /** @var iterable<number> $range */
            self::assertSame($expectedResult, IterableUtil::max($range));
        };

        $impl([1], 1);
        $impl([1.2], 1.2);
        $impl([1, 1.2], 1.2);
        $impl([1.2, 2], 2);
        $impl([1, 3.4, 2], 3.4);
        $impl([5, 1.2, 3.4], 5);
        $impl([7, 7.7, 7.8], 7.8);
    }

    /**
     * @return iterable<string, TestTakeUpToInput>
     */
    public static function dataProviderForTestTakeUpTo(): iterable
    {
        /**
         * @return iterable<TestTakeUpToArgs>
         */
        $genDataSets = function (): iterable {
            yield ['input' => ['iterable' => [], 'upTo' => 0], 'expectedOutput' => []];
        };

        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($genDataSets);
    }

    /**
     * @dataProvider dataProviderForTestTakeUpTo
     *
     * @param TestTakeUpToInput $input
     * @param iterable<mixed>   $expectedOutput
     */
    public static function testTakeUpTo(array $input, iterable $expectedOutput): void
    {
        AssertEx::sameValuesListIterables($expectedOutput, IterableUtil::takeUpTo($input['iterable'], $input['upTo']));
    }
}
