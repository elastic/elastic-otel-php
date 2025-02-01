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
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\TestCaseBase;

final class ArrayUtilTest extends TestCaseBase
{
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

    /** @noinspection PhpSameParameterValueInspection */
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
        self::assertSame('value for level 2 - a', $level1ValRef['level 2 - a']); // @phpstan-ignore staticMethod.alreadyNarrowedType
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
                self::assertSameArrays($expectedOutArray, $actualOutArrayIndexesFixed);
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
                self::assertCountAtMost(count($inArray) - 1, $expectedOutArray);
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
                self::assertSameArrays($expectedOutArray, $actualOutArrayIndexesFixed);
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
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            $actualOutput = IterableUtil::toList(ArrayUtilForTests::iterateListInReverse($input));
            $dbgCtx->add(compact('actualOutput'));
            self::assertCount(count($input), $actualOutput);
            $dbgCtx->pushSubScope();
            foreach (IterableUtil::zip($expectedOutput, $actualOutput) as [$expectedValue, $actualValue]) {
                $dbgCtx->clearCurrentSubScope(compact('expectedValue', 'actualValue'));
                self::assertSame($expectedValue, $actualValue);
            }
            $dbgCtx->popSubScope();
        } finally {
            $dbgCtx->pop();
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
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            $actualOutput = IterableUtil::toMap(ArrayUtilForTests::iterateMapInReverse($input));
            $dbgCtx->add(compact('actualOutput'));
            self::assertCount(count($input), $actualOutput);
            $dbgCtx->pushSubScope();
            foreach (IterableUtil::zip($expectedOutput, $actualOutput) as [$expectedValue, $actualValue]) {
                $dbgCtx->clearCurrentSubScope(compact('expectedValue', 'actualValue'));
                self::assertSame($expectedValue, $actualValue);
            }
            $dbgCtx->popSubScope();
        } finally {
            $dbgCtx->pop();
        }
    }
}
