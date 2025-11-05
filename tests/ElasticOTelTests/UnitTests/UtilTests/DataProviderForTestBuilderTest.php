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
use ElasticOTelTests\Util\BoolUtilForTests;
use ElasticOTelTests\Util\CombinatorialUtil;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\TestCaseBase;

class DataProviderForTestBuilderTest extends TestCaseBase
{
    private const TEST_DIMENSION_KEY = 'test_dimension_key';
    private const HELPER_DIMENSION_KEY = 'helper_dimension_key';
    private const HELPER_DIMENSION_VALUES = ['helper_dimension_value_A', 'helper_dimension_value_B'];

    /**
     * @param mixed[] $testDimensionValues
     * @param callable(): iterable<string, array<mixed>> $callToGetActualDataSets
     */
    public function assertCombinationWithHelperDimension(array $testDimensionValues, bool $onlyFirstValueCombinable, callable $callToGetActualDataSets): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $actualDataSets = IterableUtil::toMap($callToGetActualDataSets());
        $dbgCtx->add(compact('actualDataSets'));

        $expectedDataSets = [];
        if (!(ArrayUtilForTests::isEmpty($testDimensionValues) || ArrayUtilForTests::isEmpty(self::HELPER_DIMENSION_VALUES))) {
            if ($onlyFirstValueCombinable) {
                foreach ($testDimensionValues as $testDimensionValue) {
                    $expectedDataSets[] = [self::TEST_DIMENSION_KEY => $testDimensionValue, self::HELPER_DIMENSION_KEY => self::HELPER_DIMENSION_VALUES[0]];
                }
                $firstSkipped = false;
                foreach (self::HELPER_DIMENSION_VALUES as $helperDimensionValue) {
                    if (!$firstSkipped) {
                        $firstSkipped = true;
                        continue;
                    }
                    $expectedDataSets[] = [self::TEST_DIMENSION_KEY => $testDimensionValues[0], self::HELPER_DIMENSION_KEY => $helperDimensionValue];
                }
            } else {
                foreach ($testDimensionValues as $testDimensionValue) {
                    foreach (self::HELPER_DIMENSION_VALUES as $helperDimensionValue) {
                        $expectedDataSets[] = [self::TEST_DIMENSION_KEY => $testDimensionValue, self::HELPER_DIMENSION_KEY => $helperDimensionValue];
                    }
                }
            }
        }
        $dbgCtx->add(compact('expectedDataSets'));

        $expectedIterator = IterableUtil::iterableToIterator($expectedDataSets);
        $expectedIterator->rewind();
        $actualIterator = IterableUtil::iterableToIterator($actualDataSets);
        $actualIterator->rewind();

        while (true) {
            if (!$expectedIterator->valid()) {
                self::assertFalse($actualIterator->valid());
                break;
            }
            $expectedDataSet = $expectedIterator->current();
            $expectedIterator->next();
            $actualDataSetDesc = $actualIterator->key();
            $actualDataSet = $actualIterator->current();
            $actualIterator->next();
            $dbgCtx->add(compact('expectedDataSet', 'actualDataSetDesc', 'actualDataSet'));
            AssertEx::arrayHasKeyWithSameValue(self::TEST_DIMENSION_KEY, $expectedDataSet[self::TEST_DIMENSION_KEY], $actualDataSet);
            AssertEx::arrayHasKeyWithSameValue(self::HELPER_DIMENSION_KEY, $expectedDataSet[self::HELPER_DIMENSION_KEY], $actualDataSet);
        }
    }

    /**
     * @param array<mixed>|callable(): iterable<mixed> $values
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function addKeyedDimension(DataProviderForTestBuilder $builder, string $dimensionKey, bool $onlyFirstValueCombinable, array|callable $values): void
    {
        if ($onlyFirstValueCombinable) {
            $builder->addKeyedDimensionOnlyFirstValueCombinable($dimensionKey, $values);
        } else {
            $builder->addKeyedDimensionAllValuesCombinable($dimensionKey, $values);
        }
    }

    private static function addHelperKeyedDimension(DataProviderForTestBuilder $builder, bool $onlyFirstValueCombinable): void
    {
        self::addKeyedDimension($builder, self::HELPER_DIMENSION_KEY, $onlyFirstValueCombinable, self::HELPER_DIMENSION_VALUES);
    }

    /**
     * @noinspection PhpSameParameterValueInspection
     */
    private static function addNullableBoolKeyedDimension(DataProviderForTestBuilder $builder, string $dimensionKey, bool $onlyFirstValueCombinable): void
    {
        if ($onlyFirstValueCombinable) {
            $builder->addNullableBoolKeyedDimensionOnlyFirstValueCombinable($dimensionKey);
        } else {
            $builder->addNullableBoolKeyedDimensionAllValuesCombinable($dimensionKey);
        }
    }

    /**
     * @dataProvider dataProviderOneBoolArg
     */
    public function testAddNullableBoolKeyedDimension(bool $onlyFirstValueCombinable): void
    {
        $builder = new DataProviderForTestBuilder();

        self::addNullableBoolKeyedDimension($builder, self::TEST_DIMENSION_KEY, $onlyFirstValueCombinable);
        self::addHelperKeyedDimension($builder, $onlyFirstValueCombinable);
        $actual = $builder->buildAsMultiUse();

        self::assertCombinationWithHelperDimension(BoolUtilForTests::ALL_NULLABLE_VALUES, $onlyFirstValueCombinable, $actual);
    }

    public function testOneList(): void
    {
        $inputList = ['a', 'b', 'c'];
        $expected = IterableUtil::toList(CombinatorialUtil::cartesianProduct([$inputList]));
        AssertEx::equalAsSets([['a'], ['b'], ['c']], $expected);
        foreach (BoolUtilForTests::ALL_VALUES as $onlyFirstValueCombinable) {
            $actual = IterableUtil::toList(
                $onlyFirstValueCombinable
                    ? (new DataProviderForTestBuilder())
                    ->addDimensionOnlyFirstValueCombinable($inputList)
                    ->build()
                    : (new DataProviderForTestBuilder())
                    ->addDimensionAllValuesCombinable($inputList)
                    ->build()
            );
            AssertEx::equalAsSets($expected, $actual);
        }
    }

    /**
     * @return iterable<array{bool, bool}>
     */
    public static function dataProviderForTwoBoolArgs(): iterable
    {
        foreach (BoolUtilForTests::ALL_VALUES as $onlyFirstValueCombinable1) {
            foreach (BoolUtilForTests::ALL_VALUES as $onlyFirstValueCombinable2) {
                yield [$onlyFirstValueCombinable1, $onlyFirstValueCombinable2];
            }
        }
    }

    /**
     * @dataProvider dataProviderForTwoBoolArgs
     *
     * @param bool $onlyFirstValueCombinable1
     * @param bool $onlyFirstValueCombinable2
     */
    public function testTwoLists(bool $onlyFirstValueCombinable1, bool $onlyFirstValueCombinable2): void
    {
        $inputList1 = ['a', 'b'];
        $inputList2 = [1, 2, 3];
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addDimension($onlyFirstValueCombinable1, $inputList1)
                ->addDimension($onlyFirstValueCombinable2, $inputList2)
                ->build()
        );
        if ($onlyFirstValueCombinable1 && $onlyFirstValueCombinable2) {
            $expected = [
                ['a', 1],
                ['b', 1],
                ['a', 2],
                ['a', 3],
            ];
        } else {
            $expected = IterableUtil::toList(
                CombinatorialUtil::cartesianProduct([$inputList1, $inputList2])
            );
        }
        AssertEx::equalAsSets(
            $expected,
            $actual,
            LoggableToString::convert(
                [
                    'onlyFirstValueCombinable1' => $onlyFirstValueCombinable1,
                    'onlyFirstValueCombinable2' => $onlyFirstValueCombinable2,
                    '$expected'                 => $expected,
                    'actual'                    => $actual,
                ]
            )
        );
    }

    /**
     * @dataProvider dataProviderForTwoBoolArgs
     *
     * @param bool $disableInstrumentationsOnlyFirstValueCombinable
     * @param bool $dbNameOnlyFirstValueCombinable
     */
    public function testOneGeneratorAddsMultipleDimensions(
        bool $disableInstrumentationsOnlyFirstValueCombinable,
        bool $dbNameOnlyFirstValueCombinable
    ): void {
        $disableInstrumentationsVariants = [
            ''    => true,
            'pdo' => false,
            'db'  => false,
        ];
        $dbNameVariants = ['memory', '/tmp/file'];
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addGenerator(
                    $disableInstrumentationsOnlyFirstValueCombinable,
                    /**
                     * @param array<string|int, mixed> $resultSoFar
                     *
                     * @return iterable<array<string|int, mixed>>
                     */
                    function (array $resultSoFar) use ($disableInstrumentationsVariants): iterable {
                        foreach ($disableInstrumentationsVariants as $optVal => $isInstrumentationEnabled) {
                            yield array_merge($resultSoFar, [$optVal, $isInstrumentationEnabled]);
                        }
                    }
                )
                ->addDimension($dbNameOnlyFirstValueCombinable, $dbNameVariants)
                ->build()
        );

        $disableInstrumentationsVariantsPairs = [];
        foreach ($disableInstrumentationsVariants as $optVal => $isInstrumentationEnabled) {
            $disableInstrumentationsVariantsPairs[] = [$optVal, $isInstrumentationEnabled];
        }
        $cartesianProductPacked = IterableUtil::toList(
            CombinatorialUtil::cartesianProduct([$disableInstrumentationsVariantsPairs, $dbNameVariants])
        );
        // Unpack $disableInstrumentationsVariants pair in each row
        $cartesianProduct = [];
        foreach ($cartesianProductPacked as $cartesianProductPackedRow) {
            self::assertIsArray($cartesianProductPackedRow); // @phpstan-ignore staticMethod.alreadyNarrowedType
            self::assertCount(2, $cartesianProductPackedRow);
            $pair = $cartesianProductPackedRow[0];
            self::assertIsArray($pair);
            self::assertCount(2, $pair);
            $cartesianProductRow = [];
            $cartesianProductRow[] = $pair[0];
            $cartesianProductRow[] = $pair[1];
            $cartesianProductRow[] = $cartesianProductPackedRow[1];
            $cartesianProduct[] = $cartesianProductRow;
        }

        if ($disableInstrumentationsOnlyFirstValueCombinable && $dbNameOnlyFirstValueCombinable) {
            $expected = [
                ['', true, 'memory'],
                ['pdo', false, 'memory'],
                ['db', false, 'memory'],
                ['', true, '/tmp/file'],
            ];
        } else {
            $expected = $cartesianProduct;
        }

        AssertEx::equalAsSets(
            $expected,
            $actual,
            LoggableToString::convert(
                [
                    'disableInstrumentationsOnlyFirstValueCombinable'
                                                     => $disableInstrumentationsOnlyFirstValueCombinable,
                    'dbNameOnlyFirstValueCombinable' => $dbNameOnlyFirstValueCombinable,
                    '$expected'                      => $expected,
                    'actual'                         => $actual,
                ]
            )
        );
    }

    /**
     * @dataProvider dataProviderForTwoBoolArgs
     *
     * @param bool $onlyFirstValueCombinable1
     * @param bool $onlyFirstValueCombinable2
     */
    public function testTwoKeyedDimensions(bool $onlyFirstValueCombinable1, bool $onlyFirstValueCombinable2): void
    {
        $inputList1 = ['a', 'b'];
        $inputList2 = [1, 2, 3];
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addKeyedDimension('letter', $onlyFirstValueCombinable1, $inputList1)
                ->addKeyedDimension('digit', $onlyFirstValueCombinable2, $inputList2)
                ->build()
        );
        if ($onlyFirstValueCombinable1 && $onlyFirstValueCombinable2) {
            $expected = [
                ['letter' => 'a', 'digit' => 1],
                ['letter' => 'b', 'digit' => 1],
                ['letter' => 'a', 'digit' => 2],
                ['letter' => 'a', 'digit' => 3],
            ];
        } else {
            $expected = IterableUtil::toList(
                CombinatorialUtil::cartesianProduct([$inputList1, $inputList2])
            );
        }
        AssertEx::equalAsSets(
            $expected,
            $actual,
            LoggableToString::convert(
                [
                    'onlyFirstValueCombinable1' => $onlyFirstValueCombinable1,
                    'onlyFirstValueCombinable2' => $onlyFirstValueCombinable2,
                    '$expected'                 => $expected,
                    'actual'                    => $actual,
                ]
            )
        );
    }

    /**
     * @dataProvider dataProviderOneBoolArg
     */
    public function testCartesianProductKeyed(bool $dimAOnlyFirstValueCombinable): void
    {
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addKeyedDimension('dimA', $dimAOnlyFirstValueCombinable, [1.23, 4.56])
                ->addCartesianProductOnlyFirstValueCombinable(['dimB' => [1, 2, 3], 'dimC' => ['a', 'b']])
                ->build()
        );
        $expected = $dimAOnlyFirstValueCombinable
            ?
            [
                ['dimA' => 1.23, 'dimB' => 1, 'dimC' => 'a'],
                ['dimA' => 4.56, 'dimB' => 1, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 1, 'dimC' => 'b'],
                ['dimA' => 1.23, 'dimB' => 2, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 2, 'dimC' => 'b'],
                ['dimA' => 1.23, 'dimB' => 3, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 3, 'dimC' => 'b'],
            ]
            :
            [
                ['dimA' => 1.23, 'dimB' => 1, 'dimC' => 'a'],
                ['dimA' => 4.56, 'dimB' => 1, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 1, 'dimC' => 'b'],
                ['dimA' => 4.56, 'dimB' => 1, 'dimC' => 'b'],
                ['dimA' => 1.23, 'dimB' => 2, 'dimC' => 'a'],
                ['dimA' => 4.56, 'dimB' => 2, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 2, 'dimC' => 'b'],
                ['dimA' => 4.56, 'dimB' => 2, 'dimC' => 'b'],
                ['dimA' => 1.23, 'dimB' => 3, 'dimC' => 'a'],
                ['dimA' => 4.56, 'dimB' => 3, 'dimC' => 'a'],
                ['dimA' => 1.23, 'dimB' => 3, 'dimC' => 'b'],
                ['dimA' => 4.56, 'dimB' => 3, 'dimC' => 'b'],
            ];
        AssertEx::equalAsSets(
            $expected,
            $actual,
            LoggableToString::convert(['$expected' => $expected, 'actual' => $actual])
        );
    }

    /**
     * @dataProvider dataProviderOneBoolArg
     */
    public function testCartesianProduct(bool $dimAOnlyFirstValueCombinable): void
    {
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addDimension($dimAOnlyFirstValueCombinable, [1.23, 4.56])
                ->addCartesianProductOnlyFirstValueCombinable([[1, 2, 3], ['a', 'b']])
                ->build()
        );
        $expected = $dimAOnlyFirstValueCombinable
            ?
            [
                [1.23, 1, 'a'],
                [4.56, 1, 'a'],
                [1.23, 1, 'b'],
                [1.23, 2, 'a'],
                [1.23, 2, 'b'],
                [1.23, 3, 'a'],
                [1.23, 3, 'b'],
            ]
            :
            [
                [1.23, 1, 'a'],
                [4.56, 1, 'a'],
                [1.23, 1, 'b'],
                [4.56, 1, 'b'],
                [1.23, 2, 'a'],
                [4.56, 2, 'a'],
                [1.23, 2, 'b'],
                [4.56, 2, 'b'],
                [1.23, 3, 'a'],
                [4.56, 3, 'a'],
                [1.23, 3, 'b'],
                [4.56, 3, 'b'],
            ];
        AssertEx::equalAsSets(
            $expected,
            $actual,
            LoggableToString::convert(['$expected' => $expected, 'actual' => $actual])
        );
    }

    public function testConditional(): void
    {
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionAllValuesCombinable('1st_dim_key', [1, 2])
                ->addConditionalKeyedDimensionAllValueCombinable(
                    '2ns_dim_key' /* <- new dimension key */,
                    '1st_dim_key' /* <- depends on dimension key */,
                    1 /* <- depends on dimension true value */,
                    ['a'] /* <- new dimension variants for true case */,
                    ['b', 'c'] /* <- new dimension variants for false case */
                )
                ->build()
        );
        $expected =
            [
                ['1st_dim_key' => 1, '2ns_dim_key' => 'a'],
                ['1st_dim_key' => 2, '2ns_dim_key' => 'b'],
                ['1st_dim_key' => 2, '2ns_dim_key' => 'c'],
            ];
        AssertEx::equalAsSets($expected, $actual);
    }

    /**
     * @dataProvider dataProviderOneBoolArg
     */
    public function testUsingRangeForDimensionValues(bool $dimAOnlyFirstValueCombinable): void
    {
        $actual = IterableUtil::toList(
            (new DataProviderForTestBuilder())
                ->addKeyedDimension('1st_dim_key', $dimAOnlyFirstValueCombinable, DataProviderForTestBuilder::rangeUpTo(2))
                ->addKeyedDimension('2nd_dim_key', $dimAOnlyFirstValueCombinable, DataProviderForTestBuilder::rangeUpTo(2))
                ->addKeyedDimension('3rd_dim_key', $dimAOnlyFirstValueCombinable, DataProviderForTestBuilder::rangeUpTo(2))
                ->build()
        );
        $expected = $dimAOnlyFirstValueCombinable
            ?
            [
                ['1st_dim_key' => 0, '2ns_dim_key' => 0, '3rd_dim_key' => 0],
                ['1st_dim_key' => 0, '2ns_dim_key' => 0, '3rd_dim_key' => 1],
                ['1st_dim_key' => 0, '2ns_dim_key' => 1, '3rd_dim_key' => 0],
                ['1st_dim_key' => 1, '2ns_dim_key' => 0, '3rd_dim_key' => 0],
            ]
            :
            [
                ['1st_dim_key' => 0, '2ns_dim_key' => 0, '3rd_dim_key' => 0],
                ['1st_dim_key' => 0, '2ns_dim_key' => 0, '3rd_dim_key' => 1],
                ['1st_dim_key' => 0, '2ns_dim_key' => 1, '3rd_dim_key' => 0],
                ['1st_dim_key' => 0, '2ns_dim_key' => 1, '3rd_dim_key' => 1],
                ['1st_dim_key' => 1, '2ns_dim_key' => 0, '3rd_dim_key' => 0],
                ['1st_dim_key' => 1, '2ns_dim_key' => 0, '3rd_dim_key' => 1],
                ['1st_dim_key' => 1, '2ns_dim_key' => 1, '3rd_dim_key' => 0],
                ['1st_dim_key' => 1, '2ns_dim_key' => 1, '3rd_dim_key' => 1],
            ];
        AssertEx::equalAsSets($expected, $actual);
    }
}
