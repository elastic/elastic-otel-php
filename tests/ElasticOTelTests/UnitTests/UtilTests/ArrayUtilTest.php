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

use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\DisableDebugContextTestTrait;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\TestCaseBase;
use OutOfBoundsException;

/**
 * @phpstan-type TestPopNInput array{'array': array<mixed>, 'n': non-negative-int}
 * @phpstan-type TestPopNArgs array{'input': TestPopNInput, 'expectedOutput': array<mixed>}
 */
final class ArrayUtilTest extends TestCaseBase
{
    use DisableDebugContextTestTrait;

    /**
     * @param mixed[] $args
     *
     * @return void
     */
    private static function verifyArgs(array $args): void
    {
        self::assertCount(1, $args);
        $arg0 = $args[0];
        self::assertIsString($arg0);
    }

    /**
     * @param mixed[] $args
     *
     * @return void
     */
    private static function instrumentationFunc(array $args): void
    {
        self::assertCount(1, $args);
        self::verifyArgs($args);
        $someParam =& $args[0];
        self::assertSame('value set by instrumentedFunc caller', $someParam);
        $someParam = 'value set by instrumentationFunc';
    }

    private static function instrumentedFunc(string $someParam): string
    {
        self::instrumentationFunc([&$someParam]);
        return $someParam;
    }

    public static function testReferencesInArray(): void
    {
        $instrumentedFuncRetVal = self::instrumentedFunc('value set by instrumentedFunc caller');
        self::assertSame('value set by instrumentationFunc', $instrumentedFuncRetVal);
    }

    public static function testRemoveElementFromTwoLevelArrayViaReferenceToFirstLevel(): void
    {
        $myArr = [
            'level 1 - a' => [
                'level 2 - a' => 'value for level 2 - a',
                'level 2 - b' => 'value for level 2 - b',
            ]
        ];
        $level1ValRef =& $myArr['level 1 - a'];
        self::assertArrayHasKey('level 2 - a', $level1ValRef); // @phpstan-ignore staticMethod.alreadyNarrowedType
        AssertEx::sameConstValues('value for level 2 - a', $level1ValRef['level 2 - a']);
        unset($level1ValRef['level 2 - a']);
        self::assertArrayNotHasKey('level 2 - a', $myArr['level 1 - a']);
        self::assertArrayHasKey('level 2 - b', $myArr['level 1 - a']); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public static function testRemoveFirstByValue(): void
    {
        /**
         * @param list<mixed>  $inArray
         * @param ?list<mixed> $expectedOutArray
         */
        $testImpl = function (array $inArray, mixed $valueToRemove, ?array $expectedOutArray = null): void {
            if ($expectedOutArray !== null) {
                self::assertCount(count($inArray) - 1, $expectedOutArray);
            }
            $actualOutArray = $inArray;
            self::assertSame($expectedOutArray !== null, ArrayUtilForTests::removeFirstByValue(/* in,out */ $actualOutArray, $valueToRemove));
            if ($expectedOutArray === null) {
                self::assertSame($inArray, $actualOutArray);
            } else {
                self::assertNotSame($inArray, $actualOutArray);
                $actualOutArrayIndexesFixed = [...$actualOutArray];
                AssertEx::arraysHaveTheSameContent($expectedOutArray, $actualOutArrayIndexesFixed);
            }
        };

        $testImpl([], 'a');
        $testImpl(['a'], 'a', []);
        $testImpl(['a', 'b'], 'b', ['a']);
        $testImpl(['a', 'b', 'c'], 'b', ['a', 'c']);

        // The search is for identical value (i.e., using ===)
        $testImpl(['1'], 1);
        $testImpl(['1'], '1', []);
    }

    public static function testRemoveAllValues(): void
    {
        /**
         * @param list<mixed>  $inArray
         * @param list<mixed>  $valuesToRemove
         * @param ?list<mixed> $expectedOutArray
         *
         * @return void
         */
        $testImpl = function (array $inArray, array $valuesToRemove, ?array $expectedOutArray = null): void {
            if ($expectedOutArray !== null) {
                AssertEx::countAtMost(count($inArray) - 1, $expectedOutArray);
                self::assertNotEmpty($valuesToRemove);
            }
            $expectedRemovedCount = $expectedOutArray === null ? 0 : (count($inArray) - count($expectedOutArray));
            $actualOutArray = $inArray;
            self::assertSame($expectedRemovedCount, ArrayUtilForTests::removeAllValues(/* in,out */ $actualOutArray, $valuesToRemove));
            if ($expectedOutArray === null) {
                self::assertSame($inArray, $actualOutArray);
            } else {
                self::assertNotSame($inArray, $actualOutArray);
                $actualOutArrayIndexesFixed = [...$actualOutArray];
                AssertEx::arraysHaveTheSameContent($expectedOutArray, $actualOutArrayIndexesFixed);
            }
        };

        $testImpl([], []);
        $testImpl([], ['a']);

        $testImpl(['a'], ['a'], []);
        $testImpl(['a', 'b'], ['b'], ['a']);
        $testImpl(['a', 'b', 'c'], ['b'], ['a', 'c']);

        $testImpl(['a', 'b', 'c'], ['c', 'b'], ['a']);
        $testImpl(['a', 'b', 'c', 'a'], ['c', 'a'], ['b']);

        // The search is for identical value (i.e., using ===)
        $testImpl(['1'], [1]);
        $testImpl(['1'], ['1'], []);
    }

    /**
     * @return iterable<array{mixed[]}>
     */
    public static function dataProviderForTestIterateListInReverse(): iterable
    {
        yield [[], []];
        yield [[1], [1]];
        yield [['a'], ['a']];
        yield [['a',  'b'], ['b', 'a']];
        yield [[1,  2], [2, 1]];
        yield [['a',  2, 'c'], ['c',  2, 'a']];
        yield [[1,  'b', 3], [3,  'b', 1]];
    }

    /**
     * @dataProvider dataProviderForTestIterateListInReverse
     *
     * @param mixed[] $input
     * @param mixed[] $expectedOutput
     */
    public static function testIterateListInReverse(array $input, array $expectedOutput): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $actualOutput = IterableUtil::toList(ArrayUtilForTests::iterateListInReverse($input));
        $dbgCtx->add(compact('actualOutput'));
        self::assertCount(count($input), $actualOutput);
        foreach (IterableUtil::zip($expectedOutput, $actualOutput) as [$expectedValue, $actualValue]) {
            $dbgCtx->add(compact('expectedValue', 'actualValue'));
            self::assertSame($expectedValue, $actualValue);
        }
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function dataProviderForTestIterateMapInReverse(): iterable
    {
        yield [[], []];
        yield [['a' => 1], ['a' => 1]];
        yield [[1 => 'a'], [1 => 'a']];
        yield [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]];
        yield [[1 => 'a', 2 => 'b'], [2 => 'b', 1 => 'a']];
        yield [['a' => 1, 2 => 'b', 'c' => 3], ['c' => 3, 2 => 'b', 'a' => 1]];
        yield [[1 => 'a', 'b' => 2, 3 => 'c'], [3 => 'c', 'b' => 2, 1 => 'a']];
    }

    /**
     * @dataProvider dataProviderForTestIterateMapInReverse
     *
     * @param array<array-key, mixed> $input
     * @param array<array-key, mixed> $expectedOutput
     */
    public static function testIterateMapInReverse(array $input, array $expectedOutput): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $actualOutput = IterableUtil::toMap(ArrayUtilForTests::iterateMapInReverse($input));
        $dbgCtx->add(compact('actualOutput'));
        self::assertCount(count($input), $actualOutput);
        foreach (IterableUtil::zip($expectedOutput, $actualOutput) as [$expectedValue, $actualValue]) {
            $dbgCtx->add(compact('expectedValue', 'actualValue'));
            self::assertSame($expectedValue, $actualValue);
        }
    }

    /**
     * @template T
     *
     * @param array<T>         $input
     * @param non-negative-int $numberOfElementsToPop
     *
     * @return array<T>
     */
    private static function popNOnCopy(array $input, int $numberOfElementsToPop): array
    {
        ArrayUtilForTests::popN(/* in,out */ $input, $numberOfElementsToPop);
        return $input;
    }

    /**
     * @return iterable<string, TestPopNArgs>
     */
    public static function dataProviderForTestPopNForValidInput(): iterable
    {
        /**
         * @return iterable<TestPopNArgs>
         */
        $genDataSets = function (): iterable {
            yield ['input' => ['array' => [], 'n' => 0], 'expectedOutput' => []];
            yield ['input' => ['array' => ['a'], 'n' => 1], 'expectedOutput' => []];
            yield ['input' => ['array' => ['a', 'b'], 'n' => 2], 'expectedOutput' => []];
            yield ['input' => ['array' => ['a', 'b', 'c'], 'n' => 3], 'expectedOutput' => []];

            yield ['input' => ['array' => ['a'], 'n' => 0], 'expectedOutput' => ['a']];
            yield ['input' => ['array' => ['a', 'b'], 'n' => 0], 'expectedOutput' => ['a', 'b']];
            yield ['input' => ['array' => ['a', 'b', 'c'], 'n' => 0], 'expectedOutput' => ['a', 'b', 'c']];

            yield ['input' => ['array' => ['a', 'b'], 'n' => 1], 'expectedOutput' => ['a']];

            yield ['input' => ['array' => ['a', 'b', 'c'], 'n' => 1], 'expectedOutput' => ['a', 'b']];
            yield ['input' => ['array' => ['a', 'b', 'c'], 'n' => 2], 'expectedOutput' => ['a']];
        };

        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($genDataSets);
    }

    /**
     * @dataProvider dataProviderForTestPopNForValidInput
     *
     * @param TestPopNInput $input
     * @param array<mixed>  $expectedOutput
     */
    public static function testPopNForValidInput(array $input, array $expectedOutput): void
    {
        AssertEx::sameValuesListIterables($expectedOutput, self::popNOnCopy($input['array'], $input['n']));
    }

    /**
     * @return iterable<array{callable(): mixed}>
     */
    public static function dataProviderForTestPopNForInvalidInput(): iterable
    {
        yield [fn() => self::popNOnCopy([], 1)];
        yield [fn() => self::popNOnCopy(['a'], 2)];
        yield [fn() => self::popNOnCopy(['a', 'b'], 3)];
        yield [fn() => self::popNOnCopy(['a', 'b', 'c'], 4)];
        yield [fn() => self::popNOnCopy(['a', 'b', 'c'], 5)];
    }

    /**
     * @dataProvider dataProviderForTestPopNForInvalidInput
     *
     * @param callable(): mixed $callThatThrows
     */
    public static function testPopNForInvalidInput(callable $callThatThrows): void
    {
        AssertEx::throws(OutOfBoundsException::class, $callThatThrows);
    }

    public static function testRemoveByKeys(): void
    {
        /**
         * @param array<array-key, mixed> $removeFromArray
         * @param iterable<array-key>     $keys
         */
        $testImpl = function (array $removeFromArray, iterable $keys, array $expectedResult): void {
            $actualResult = $removeFromArray;
            /** @var iterable<array-key> $keys */
            ArrayUtilForTests::removeByKeys($actualResult, $keys);
            AssertEx::equalMaps($expectedResult, $actualResult);
        };

        $testImpl([], [], []);
        $testImpl([], ['key_a'], []);
        $testImpl(['key_a' => 'value_a'], ['key_a'], []);
        $testImpl(['key_a' => 'value_a'], ['key_b'], ['key_a' => 'value_a']);
        $testImpl(['key_a' => 'value_a', 'key_b' => 'value_b'], ['key_b'], ['key_a' => 'value_a']);
        $testImpl(['key_a' => 'value_a', 'key_b' => 'value_b'], ['key_a'], ['key_b' => 'value_b']);
        $testImpl(['value_0', 'value_1'], [0], [1 => 'value_1']);
        $testImpl(['value_0', 'value_1'], [1], ['value_0']);
    }
}
