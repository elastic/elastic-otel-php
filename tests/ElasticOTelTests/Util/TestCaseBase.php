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

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ElasticOTelTests\Util;

use ArrayAccess;
use Countable;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\Logger;
use Exception;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;

class TestCaseBase extends TestCase
{
    /** 10 milliseconds (10000 microseconds) precision */
    public const TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS = 10000;

    public const DURATION_COMPARISON_PRECISION_MILLISECONDS = self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS / 1000;

    /**
     * Asserts that the callable throws a specified throwable.
     * If successful and the inspection callable is not null
     * then it is called and the caught exception is passed as argument.
     */
    public static function assertThrows(string $class, callable $execute, string $message = '', ?callable $inspect = null): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            try {
                $execute();
            } catch (ExpectationFailedException $ex) {
                throw $ex;
            } catch (Throwable $ex) {
                Assert::assertThat($ex, new ConstraintException($class), $message);

                if ($inspect !== null) {
                    $inspect($ex);
                }

                return;
            }

            Assert::assertThat(null, new ConstraintException($class), $message);
        } finally {
            $dbgCtx->pop();
        }
    }

    /**
     * @param array<array-key, mixed> $subSet
     * @param array<array-key, mixed> $largerSet
     */
    public static function assertListArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        DebugContextForTests::newScope(
            $dbgCtx,
            [
                'array_diff'             => array_diff($subSet, $largerSet),
                'count(array_intersect)' => count(array_intersect($subSet, $largerSet)),
                'count($subSet)'         => count($subSet),
                'array_intersect'        => array_intersect($subSet, $largerSet),
                '$subSet'                => $subSet,
                '$largerSet'             => $largerSet,
            ]
        );
        self::assertTrue(count(array_intersect($subSet, $largerSet)) === count($subSet));
    }

    public static function assertSameEx(mixed $expected, mixed $actual, string $message = ''): void
    {
        /**
         * @param mixed $value
         *
         * @return bool
         */
        $isNumeric = function (mixed $value): bool {
            return is_float($value) || is_int($value);
        };
        if ($isNumeric($expected) && $isNumeric($actual) && (is_float($expected) !== is_float($actual))) {
            /** @var int|float $expected */
            /** @var int|float $actual */
            self::assertSame(floatval($expected), floatval($actual), $message);
        } else {
            self::assertSame($expected, $actual, $message);
        }
    }

    /**
     * @param array<array-key, mixed> $subSet
     * @param array<array-key, mixed> $largerSet
     */
    public static function assertMapArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        $dbgCtx->pushSubScope();
        foreach ($subSet as $key => $value) {
            $dbgCtx->clearCurrentSubScope(['$key' => $key, '$value' => $value]);
            self::assertArrayHasKey($key, $largerSet);
            self::assertSameEx($value, $largerSet[$key]);
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pop();
    }

    /**
     * @param array<array-key, mixed> $actual
     */
    public static function assertArrayIsList(array $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            self::assertTrue(array_is_list($actual));
        } finally {
            $dbgCtx->pop();
        }
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualLists(array $expected, array $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertSame(count($expected), count($actual));
        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($expected)) as $i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            self::assertSame($expected[$i], $actual[$i]);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function assertEqualAsSets(array $expected, array $actual, string $message = ''): void
    {
        sort(/* ref */ $expected);
        sort(/* ref */ $actual);
        self::assertEqualsCanonicalizing($expected, $actual, $message);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $subsetMap
     * @param array<TKey, TValue> $containingMap
     */
    public static function assertMapIsSubsetOf(array $subsetMap, array $containingMap): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertGreaterThanOrEqual(count($subsetMap), count($containingMap));
        $dbgCtx->pushSubScope();
        foreach ($subsetMap as $subsetMapKey => $subsetMapVal) {
            $dbgCtx->clearCurrentSubScope(['subsetMapKey' => $subsetMapKey, 'subsetMapVal' => $subsetMapVal]);
            self::assertArrayHasKey($subsetMapKey, $containingMap);
            self::assertEquals($subsetMapVal, $containingMap[$subsetMapKey]);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $expected
     * @param array<TKey, TValue> $actual
     */
    public static function assertEqualMaps(array $expected, array $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertSameCount($expected, $actual);
        self::assertMapIsSubsetOf($expected, $actual);
    }

    public static function assertEqualRecursively(mixed $expected, mixed $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        if (is_array($actual)) {
            self::assertIsArray($expected);
            self::assertEqualMaps($expected, $actual);
            return;
        }

        self::assertEquals($expected, $actual);
    }

    /**
     * @param array<string|int, mixed> $idToXyzMap
     *
     * @return string[]
     */
    public static function getIdsFromIdToMap(array $idToXyzMap): array
    {
        /** @var string[] $result */
        $result = [];
        foreach ($idToXyzMap as $id => $_) {
            $result[] = strval($id);
        }
        return $result;
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    public static function getLoggerStatic(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST, $namespace, $fqClassName, $srcCodeFile);
    }

    public static function dummyAssert(): bool
    {
        self::assertTrue(true); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        return true;
    }

    protected static function addDebugContextToException(Exception $ex): void
    {
        $formattedContextsStack = LoggableToString::convert(DebugContextForTests::getContextsStack(), prettyPrint: true);
        DebugContextExceptionHelper::setMessage(
            $ex,
            $ex->getMessage()
            . "\n" . 'DebugContext begin' . "\n"
            . $formattedContextsStack
            . "\n" . 'DebugContext end'
        );
    }

    public static function assertSameNullness(mixed $expected, mixed $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertSame($expected === null, $actual === null);
    }

    /**
     * @phpstan-assert int|float $actual
     */
    public static function assertIsNumber(mixed $actual): void
    {
        self::assertThat($actual, Assert::logicalOr(new IsType(IsType::TYPE_INT), new IsType(IsType::TYPE_FLOAT)));
    }

    /**
     * @template T of int|float
     *
     * @phpstan-param T $rangeBegin
     * @phpstan-param T $actual
     * @phpstan-param T $rangeEnd
     */
    public static function assertInClosedRange(int|float $rangeBegin, int|float $actual, int|float $rangeEnd): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertGreaterThanOrEqual($rangeBegin, $actual);
        self::assertLessThanOrEqual($rangeEnd, $actual);
    }

    public static function assertLessThanOrEqualTimestamp(float $before, float $after): void
    {
        DebugContextForTests::newScope(
            $dbgCtx,
            [
                'before'         => TimeUtil::timestampToLoggable($before),
                'after'          => TimeUtil::timestampToLoggable($after),
                'after - before' => TimeUtil::timestampToLoggable($after - $before),
            ]
        );
        self::assertThat($before, Assert::logicalOr(new IsEqual($after, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS), new LessThan($after)));
    }

    public static function assertTimestampInRange(float $pastTimestamp, float $timestamp, float $futureTimestamp): void
    {
        self::assertLessThanOrEqualTimestamp($pastTimestamp, $timestamp);
        self::assertLessThanOrEqualTimestamp($timestamp, $futureTimestamp);
    }

    /**
     * @template T
     *
     * @param Optional<T> $expected
     * @param T           $actual
     */
    public static function assertSameExpectedOptional(Optional $expected, mixed $actual): void
    {
        if ($expected->isValueSet()) {
            self::assertSame($expected->getValue(), $actual);
        }
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param TKey                $expectedKey
     * @param TValue              $expectedVal
     * @param array<TKey, TValue> $actualArray
     */
    public static function assertSameValueInArray($expectedKey, $expectedVal, array $actualArray): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertSame($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @param array<string, mixed> $actualArray
     */
    public static function assertEqualValueInArray(string $expectedKey, mixed $expectedVal, array $actualArray): void
    {
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertEquals($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @template T of int|float
     *
     * @param T $rangeBegin
     * @param T $val
     * @param T $rangeInclusiveEnd
     *
     * @return void
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function assertInRangeInclusive($rangeBegin, $val, $rangeInclusiveEnd): void
    {
        self::assertGreaterThanOrEqual($rangeBegin, $val);
        self::assertLessThanOrEqual($rangeInclusiveEnd, $val);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array-key                                     $key
     * @param TValue                                        $expectedValue
     * @param array<TKey, TValue>|ArrayAccess<TKey, TValue> $array
     *
     * @phpstan-param TKey                                  $key
     */
    public static function assertArrayHasKeyWithValue(string|int $key, mixed $expectedValue, array|ArrayAccess $array, string $message = ''): void
    {
        self::assertArrayHasKey($key, $array, $message);
        self::assertSame($expectedValue, $array[$key], $message);
    }

    /**
     * @param Countable|array<array-key, mixed> $expected
     * @param Countable|array<array-key, mixed> $actual
     */
    public static function assertSameCount(Countable|array $expected, Countable|array $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertSame(count($expected), count($actual));
    }

    #[Override]
    public function setUp(): void
    {
        parent::setUp();
    }

    #[Override]
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @param iterable<array<array-key, mixed>> $srcDataProvider
     *
     * @return iterable<string, array<array-key, mixed>>
     */
    protected static function wrapDataProviderFromKeyValueMapToNamedDataSet(iterable $srcDataProvider): iterable
    {
        $dataSetIndex = 0;
        foreach ($srcDataProvider as $namedValuesMap) {
            $dataSetName = '#' . $dataSetIndex;
            $dataSetName .= ' ' . LoggableToString::convert($namedValuesMap);
            yield $dataSetName => array_values($namedValuesMap);
            ++$dataSetIndex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        try {
            if (is_object($expected) && method_exists($expected, 'equals')) {
                self::assertTrue($expected->equals($actual));
            } else {
                Assert::assertEquals($expected, $actual, $message);
            }
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @return never
     * @psalm-return never-return
     */
    #[Override]
    public static function fail(string $message = ''): void
    {
        try {
            Assert::fail($message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertTrue(mixed $condition, string $message = ''): void
    {
        try {
            Assert::assertTrue($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key, mixed>|Countable $haystack
     */
    #[Override]
    public static function assertCount(int $expectedCount, $haystack, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        $dbgCtx->add(['haystack count' => count($haystack)]);
        try {
            Assert::assertCount($expectedCount, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    /**
     * @param array<array-key, mixed>|Countable $haystack
     */
    public static function assertCountAtLeast(int $expectedMinCount, mixed $haystack): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertGreaterThanOrEqual($expectedMinCount, count($haystack));
        $dbgCtx->pop();
    }

    /**
     * @param array<array-key, mixed>|Countable $haystack
     */
    public static function assertCountAtMost(int $expectedMaxCount, mixed $haystack): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertLessThanOrEqual($expectedMaxCount, count($haystack));
        $dbgCtx->pop();
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     */
    public static function assertSameArrays(array $expected, array $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        self::assertSame(count($expected), count($actual));
        $dbgCtx->pushSubScope();
        foreach ($expected as $expectedKey => $expectedVal) {
            self::assertSameValueInArray($expectedKey, $expectedVal, $actual);
        }
        $dbgCtx->popSubScope();
        $dbgCtx->pop();
    }

    /**
     * @param Countable|array<array-key, mixed> $haystack
     */
    public static function assertCountableNotEmpty(Countable|array $haystack): void
    {
        self::assertCountAtLeast(1, $haystack);
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    #[Override]
    public static function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertGreaterThanOrEqual($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertNotFalse(mixed $condition, string $message = ''): void
    {
        try {
            Assert::assertNotFalse($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertNotNull(mixed $actual, string $message = ''): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function () use ($actual, $message): void {
                try {
                    Assert::assertNotNull($actual, $message);
                } catch (AssertionFailedError $ex) {
                    self::addDebugContextToException($ex);
                    throw $ex;
                }
            }
        );
    }

    /**
     * @template T
     *
     * @param ?T $actual
     *
     * @phpstan-return T
     */
    public static function assertNotNullAndReturn(mixed $actual, string $message = ''): mixed
    {
        self::assertNotNull($actual, $message);
        return $actual;
    }

    public static function assertIsBoolAndReturn(mixed $actual, string $message = ''): bool
    {
        self::assertIsBool($actual, $message);
        return $actual;
    }

    public static function assertStringIsIntAndReturn(string $actual, string $message = ''): int
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            self::assertNotFalse(filter_var($actual, FILTER_VALIDATE_INT), $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
        return intval($actual);
    }

    /** @inheritDoc */
    #[Override]
    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            Assert::assertNotSame($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    /** @inheritDoc */
    #[Override]
    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            Assert::assertSame($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key, mixed>|ArrayAccess<array-key, mixed> $array
     */
    #[Override]
    public static function assertArrayHasKey(mixed $key, mixed $array, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            Assert::assertArrayHasKey($key, $array, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key, mixed> $array
     */
    #[Override]
    public static function assertArrayNotHasKey(mixed $key, mixed $array, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            Assert::assertArrayNotHasKey($key, $array, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    #[Override]
    public static function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertGreaterThan($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertNull(mixed $actual, string $message = ''): void
    {
        try {
            Assert::assertNull($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertIsString(mixed $actual, string $message = ''): void
    {
        try {
            Assert::assertIsString($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    public static function assertIsStringAndReturn(mixed $actual, string $message = ''): string
    {
        self::assertIsString($actual, $message);
        return $actual;
    }

    /** @inheritDoc */
    #[Override]
    public static function assertThat(mixed $value, Constraint $constraint, string $message = ''): void
    {
        try {
            Assert::assertThat($value, $constraint, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    #[Override]
    public static function assertIsInt($actual, string $message = ''): void
    {
        try {
            Assert::assertIsInt($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    #[Override]
    public static function assertIsBool($actual, string $message = ''): void
    {
        try {
            Assert::assertIsBool($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    #[Override]
    public static function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertLessThanOrEqual($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $actual
     */
    #[Override]
    public static function assertIsArray($actual, string $message = ''): void
    {
        try {
            Assert::assertIsArray($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertEmpty(mixed $actual, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            Assert::assertEmpty($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        try {
            Assert::assertNotEmpty($actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key, mixed>|Countable $haystack
     */
    #[Override]
    public static function assertNotCount(int $expectedCount, $haystack, string $message = ''): void
    {
        try {
            Assert::assertNotCount($expectedCount, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param int|float  $expected
     * @param int|float  $actual
     */
    #[Override]
    public static function assertLessThan($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertLessThan($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $condition
     */
    #[Override]
    public static function assertFalse($condition, string $message = ''): void
    {
        try {
            Assert::assertFalse($condition, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key, mixed>|Countable $expected
     * @param array<array-key, mixed>|Countable $actual
     */
    #[Override]
    public static function assertSameSize($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertSameSize($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    #[Override]
    public static function assertEqualsCanonicalizing($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertEqualsCanonicalizing($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @template TExpected of object
     *
     * @param class-string<TExpected> $expected
     * @param mixed                   $actual
     *
     * @phpstan-assert TExpected $actual
     */
    #[Override]
    public static function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertInstanceOf($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    public static function assertNotEqualsEx(mixed $expected, mixed $actual, string $message = ''): void
    {
        try {
            Assert::assertNotEquals($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param iterable<mixed> $haystack
     */
    #[Override]
    public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        try {
            Assert::assertContains($needle, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    public static function assertDirectoryDoesNotExist(string $directory, string $message = ''): void
    {
        try {
            Assert::assertDirectoryDoesNotExist($directory, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    /** @inheritDoc */
    #[Override]
    public static function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            Assert::assertStringStartsWith($prefix, $string, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }

        $dbgCtx->pop();
    }

    public static function assertStringSameAsInt(int $expected, string $actual, string $message = ''): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            self::assertNotFalse(filter_var($actual, FILTER_VALIDATE_INT), $message);
            $actualAsInt = intval($actual);
            Assert::assertSame($expected, $actualAsInt, $message);
        } catch (AssertionFailedError $ex) {
            self::addDebugContextToException($ex);
            throw $ex;
        }
    }

    private const VERY_LONG_STRING_BASE_PREFIX = '<very long string prefix';
    private const VERY_LONG_STRING_BASE_SUFFIX = 'very long string suffix>';

    /**
     * @param positive-int $length
     */
    public static function generateVeryLongString(int $length): string
    {
        $midLength = $length - (strlen(self::VERY_LONG_STRING_BASE_PREFIX) + strlen(self::VERY_LONG_STRING_BASE_SUFFIX));
        self::assertGreaterThanOrEqual(0, $midLength);
        return self::VERY_LONG_STRING_BASE_PREFIX . str_repeat('-', $midLength) . self::VERY_LONG_STRING_BASE_SUFFIX;
    }
}
