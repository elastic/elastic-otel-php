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

namespace ElasticOTelTests\Util;

use ArrayAccess;
use Countable;
use Elastic\OTel\Util\StaticClassTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Exception as ConstraintException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\ExpectationFailedException;
use Throwable;

final class AssertEx
{
    use StaticClassTrait;

    /** 10 milliseconds (10,000 microseconds) precision */
    public const TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS = 10000;

    /** @noinspection PhpUnused */
    public const DURATION_COMPARISON_PRECISION_MILLISECONDS = self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS / 1000;

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $expected
     * @param array<TKey, TValue> $actual
     */
    public static function equalMaps(array $expected, array $actual): void
    {
        self::sameCount($expected, $actual);
        self::mapIsSubsetOf($expected, $actual);
    }

    /** @noinspection PhpUnused */
    public static function isBool(mixed $actual, string $message = ''): bool
    {
        Assert::assertIsBool($actual, $message);
        return $actual;
    }

    public static function stringSameAsInt(int $expected, string $actual, string $message = ''): void
    {
        Assert::assertNotFalse(filter_var($actual, FILTER_VALIDATE_INT), $message);
        $actualAsInt = intval($actual);
        Assert::assertSame($expected, $actualAsInt, $message);
    }

    /** @noinspection PhpUnused */
    public static function sameNullness(mixed $expected, mixed $actual): void
    {
        Assert::assertSame($expected === null, $actual === null);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey $expectedKey
     * @phpstan-param TValue $expectedValue
     * @phpstan-param array<TKey, TValue>|ArrayAccess<TKey, TValue> $actualArray
     */
    public static function arrayHasKeyWithSameValue(string|int $expectedKey, mixed $expectedValue, array|ArrayAccess $actualArray, string $message = ''): void
    {
        Assert::assertArrayHasKey($expectedKey, $actualArray, $message);
        Assert::assertSame($expectedValue, $actualArray[$expectedKey], $message);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey $expectedKey
     * @phpstan-param TValue $expectedValue
     * @phpstan-param array<TKey, TValue>|ArrayAccess<TKey, TValue> $actualArray
     *
     * @noinspection PhpUnused
     */
    public static function arrayHasKeyWithEqualValue(string|int $expectedKey, mixed $expectedValue, array|ArrayAccess $actualArray): void
    {
        Assert::assertArrayHasKey($expectedKey, $actualArray);
        Assert::assertEquals($expectedValue, $actualArray[$expectedKey]);
    }

    /**
     * @template T
     *
     * @param ?T $actual
     *
     * @phpstan-return T
     *
     * @phpstan-assert !null $actual
     */
    public static function notNull(mixed $actual, string $message = ''): mixed
    {
        Assert::assertNotNull($actual, $message);
        return $actual;
    }

    /**
     * @param string $actual
     * @param string $message
     *
     * @return non-empty-string
     *
     * @phpstan-assert non-empty-string $actual
     */
    public static function notEmptyString(string $actual, string $message = ''): string
    {
        Assert::assertNotEmpty($actual, $message);
        return $actual;
    }

    /**
     * @template T
     *
     * @param array<T> $actual
     *
     * @return non-empty-array<T>
     *
     * @phpstan-assert non-empty-array<T> $actual
     *
     * @noinspection PhpUnused
     */
    public static function notEmptyArray(array $actual, string $message = ''): array
    {
        Assert::assertNotEmpty($actual, $message);
        return $actual;
    }

    /**
     * @template T
     *
     * @param list<T> $actual
     *
     * @return non-empty-list<T>
     *
     * @phpstan-assert non-empty-list<T> $actual
     */
    public static function notEmptyList(array $actual, string $message = ''): array
    {
        Assert::assertNotEmpty($actual, $message);
        return $actual;
    }

    /**
     * @param Countable|array<array-key, mixed> $expected
     * @param Countable|array<array-key, mixed> $actual
     */
    public static function sameCount(Countable|array $expected, Countable|array $actual): void
    {
        Assert::assertSame(count($expected), count($actual));
    }

    public static function isString(mixed $actual, string $message = ''): string
    {
        Assert::assertIsString($actual, $message);
        return $actual;
    }

    public static function isNullableString(mixed $actual, string $message = ''): ?string
    {
        return $actual === null ? null : self::isString($actual, $message);
    }

    /**
     * @noinspection PhpUnused
     *
     * @return non-empty-string
     */
    public static function isNonEmptyString(mixed $actual, string $message = ''): string
    {
        Assert::assertIsString($actual, $message);
        Assert::assertNotEmpty($actual, $message);
        return $actual;
    }

    public static function isInt(mixed $actual, string $message = ''): int
    {
        Assert::assertIsInt($actual, $message);
        return $actual;
    }

    /**
     * @return non-negative-int
     */
    public static function isNonNegativeInt(mixed $actual, string $message = ''): int
    {
        Assert::assertIsInt($actual, $message);
        Assert::assertGreaterThanOrEqual(0, $actual, $message);
        return $actual; // @phpstan-ignore return.type
    }

    /**
     * @return positive-int
     */
    public static function isPositiveInt(mixed $actual, string $message = ''): int
    {
        Assert::assertIsInt($actual, $message);
        Assert::assertGreaterThan(0, $actual, $message);
        return $actual; // @phpstan-ignore return.type
    }

    public static function isFloat(mixed $actual, string $message = ''): float
    {
        Assert::assertIsFloat($actual, $message);
        return $actual;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function isArray(mixed $actual, string $message = ''): array
    {
        Assert::assertIsArray($actual, $message);
        return $actual;
    }

    /**
     * @template TArrayValue of object
     *
     * @param class-string<TArrayValue> $expectedValueType
     *
     * @phpstan-assert array<array-key, TArrayValue> $actual
     */
    final public static function isArrayWithValueType(string $expectedValueType, mixed $actual, string $message = ''): void
    {
        Assert::assertIsArray($actual, $message);
        foreach ($actual as $value) {
            Assert::assertInstanceOf($expectedValueType, $value);
        }
    }

    /**
     * @template T of numeric|string|object|resource
     *
     * @param ?T $actual
     *
     * @return T
     *
     * @phpstan-assert !null $actual
     */
    public static function isNotNull(mixed $actual, string $message = ''): mixed
    {
        Assert::assertNotNull($actual, $message);
        return $actual;
    }

    /**
     * @return null
     */
    public static function isNull(mixed $actual, string $message = '')
    {
        Assert::assertNull($actual, $message);
        return null;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $subsetMap
     * @param array<TKey, TValue> $containingMap
     */
    public static function mapIsSubsetOf(array $subsetMap, array $containingMap): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        Assert::assertGreaterThanOrEqual(count($subsetMap), count($containingMap));
        foreach ($subsetMap as $subsetMapKey => $subsetMapVal) {
            $dbgCtx->add(compact('subsetMapKey', 'subsetMapVal'));
            Assert::assertArrayHasKey($subsetMapKey, $containingMap);
            Assert::assertEquals($subsetMapVal, $containingMap[$subsetMapKey]);
        }
    }

    public static function equalsEx(mixed $expected, mixed $actual, string $message = ''): void
    {
        if (is_object($expected) && method_exists($expected, 'equals')) {
            Assert::assertTrue($expected->equals($actual));
        } else {
            Assert::assertEquals($expected, $actual, $message);
        }
    }

    /**
     * Asserts that the callable throws a specified throwable.
     * If successful and the $inspect is not null,
     * then it is called and the caught exception is passed as argument.
     *
     * @param callable(): mixed $execute
     * @param ?callable(Throwable): void $inspect
     */
    public static function throws(string $class, callable $execute, string $message = '', ?callable $inspect = null): void
    {
        try {
            $execute();
        } catch (ExpectationFailedException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            Assert::assertThat($ex, new ConstraintException($class), $message);

            if ($inspect === null) {
                return;
            }

            $inspect($ex);
        }

        Assert::assertThat(null, new ConstraintException($class), $message);
    }

    /**
     * @param array<array-key, mixed>|Countable $haystack
     *
     * @noinspection PhpUnused
     */
    public static function countAtLeast(int $expectedMinCount, mixed $haystack, string $message = ''): void
    {
        Assert::assertGreaterThanOrEqual($expectedMinCount, count($haystack), $message);
    }

    public static function stringIsInt(string $actual, string $message = ''): int
    {
        Assert::assertNotFalse(filter_var($actual, FILTER_VALIDATE_INT), $message);
        return intval($actual);
    }

    /**
     * @template TValue
     *
     * @param array<TValue> $actual
     *
     * @phpstan-assert list<TValue> $actual
     *
     * @return list<TValue>
     */
    public static function arrayIsList(array $actual): array
    {
        Assert::assertIsList($actual);
        return $actual;
    }

    /**
     * @template TValue
     *
     * @param array<TValue> $actual
     *
     * @phpstan-assert non-empty-list<TValue> $actual
     *
     * @return non-empty-list<TValue>
     */
    public static function arrayIsNotEmptyList(array $actual): array
    {
        return self::notEmptyList(self::arrayIsList($actual));
    }

    public static function sameEx(mixed $expected, mixed $actual, string $message = ''): void
    {
        $isNumeric = function (mixed $value): bool {
            return is_float($value) || is_int($value);
        };

        if ($isNumeric($expected) && $isNumeric($actual) && (is_float($expected) !== is_float($actual))) {
            /** @var int|float $expected */
            /** @var int|float $actual */
            Assert::assertSame(floatval($expected), floatval($actual), $message);
        } else {
            Assert::assertSame($expected, $actual, $message);
        }
    }

    /**
     * @template T
     *
     * @phpstan-param T $expected
     * @phpstan-param T $actual
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public static function sameConstValues(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::sameEx($expected, $actual);
    }

    /**
     * @param Countable|array<array-key, mixed> $haystack
     *
     * @noinspection PhpUnused
     */
    public static function countableNotEmpty(Countable|array $haystack): void
    {
        self::countAtLeast(1, $haystack);
    }

    /** @noinspection PhpUnused */
    public static function equalRecursively(mixed $expected, mixed $actual): void
    {
        if (is_array($actual)) {
            Assert::assertIsArray($expected);
            self::equalMaps($expected, $actual);
        } else {
            Assert::assertEquals($expected, $actual);
        }
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function equalLists(array $expected, array $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        Assert::assertSame(count($expected), count($actual));
        foreach (RangeUtil::generateUpTo(count($expected)) as $i) {
            $dbgCtx->add(compact('i'));
            Assert::assertSame($expected[$i], $actual[$i]);
        }
    }

    /**
     * @param array<array-key, mixed> $subSet
     * @param array<array-key, mixed> $largerSet
     */
    public static function listIsSubsetOf(array $subSet, array $largerSet): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $subSetCount = count($subSet);
        $dbgCtx->add(compact('subSetCount'));
        $largerSetCount = count($largerSet);
        $dbgCtx->add(compact('largerSetCount'));
        $inSubSetButNotInLarger = array_diff($subSet, $largerSet);
        $dbgCtx->add(compact('inSubSetButNotInLarger'));
        $intersection = array_intersect($subSet, $largerSet);
        $dbgCtx->add(compact('intersection'));
        $intersectionCount = count($intersection);
        $dbgCtx->add(compact('intersectionCount'));

        Assert::assertSame(count(array_intersect($subSet, $largerSet)), count($subSet));
    }

    /**
     * @param array<array-key, mixed> $subSet
     * @param array<array-key, mixed> $largerSet
     *
     * @noinspection PhpUnused
     */
    public static function mapArrayIsSubsetOf(array $subSet, array $largerSet): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        foreach ($subSet as $key => $value) {
            $dbgCtx->add(compact('key', 'value'));
            Assert::assertArrayHasKey($key, $largerSet);
            self::sameEx($value, $largerSet[$key]);
        }
    }

    /**
     * @param array<array-key, mixed>|Countable $haystack
     *
     * @noinspection PhpUnused
     */
    public static function countAtMost(int $expectedMaxCount, mixed $haystack): void
    {
        Assert::assertLessThanOrEqual($expectedMaxCount, count($haystack));
    }

    /**
     * @template T
     *
     * @param Optional<T> $expected
     * @param T           $actual
     *
     * @noinspection PhpUnused
     */
    public static function sameAsExpectedOptionalIfSet(Optional $expected, mixed $actual): void
    {
        if ($expected->isValueSet()) {
            Assert::assertSame($expected->getValue(), $actual);
        }
    }

    public static function lessThanOrEqualTimestamp(float $before, float $after): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $dbgCtx->add(['before' => TimeUtil::timestampToLoggable($before)]);
        $dbgCtx->add(['after' => TimeUtil::timestampToLoggable($after)]);
        $dbgCtx->add(['after - before' => TimeUtil::timestampToLoggable($after - $before)]);
        Assert::assertThat($before, Assert::logicalOr(new IsEqual($after, /* delta: */ self::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS), new LessThan($after)));
    }

    /** @noinspection PhpUnused */
    public static function timestampInRange(float $pastTimestamp, float $timestamp, float $futureTimestamp): void
    {
        self::lessThanOrEqualTimestamp($pastTimestamp, $timestamp);
        self::lessThanOrEqualTimestamp($timestamp, $futureTimestamp);
    }

    /**
     * @phpstan-assert int|float $actual
     *
     * @noinspection PhpUnused
     */
    public static function isNumber(mixed $actual): void
    {
        Assert::assertThat($actual, Assert::logicalOr(new IsType(IsType::TYPE_INT), new IsType(IsType::TYPE_FLOAT)));
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    public static function equalAsSets(array $expected, array $actual, string $message = ''): void
    {
        sort(/* ref */ $expected);
        sort(/* ref */ $actual);
        Assert::assertEqualsCanonicalizing($expected, $actual, $message);
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     *
     * @noinspection PhpUnused
     */
    public static function arraysHaveTheSameContent(array $expected, array $actual): void
    {
        Assert::assertSame(count($expected), count($actual));
        foreach ($expected as $expectedKey => $expectedVal) {
            self::arrayHasKeyWithSameValue($expectedKey, $expectedVal, $actual);
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $expected
     * @param iterable<T> $actual
     */
    public static function sameValuesListIterables(iterable $expected, iterable $actual): void
    {
        $expectedIterator = IterableUtil::iterableToIterator($expected);
        $expectedIterator->rewind();
        $actualIterator = IterableUtil::iterableToIterator($actual);
        $actualIterator->rewind();

        while ($expectedIterator->valid()) {
            Assert::assertTrue($actualIterator->valid());
            Assert::assertSame($expectedIterator->current(), $actualIterator->current());
            $expectedIterator->next();
            $actualIterator->next();
        }
        Assert::assertFalse($actualIterator->valid());
    }

    /**
     * @template T of int|float
     *
     * @phpstan-param T $rangeBegin
     * @phpstan-param T $actual
     * @phpstan-param T $rangeInclusiveEnd
     */
    public static function inClosedRange(int|float $rangeBegin, int|float $actual, int|float $rangeInclusiveEnd): void
    {
        Assert::assertTrue(RangeUtil::isInClosedRange($rangeBegin, $actual, $rangeInclusiveEnd));
    }

    /**
     * @param array<mixed>|Countable $container
     *
     * @phpstan-assert non-negative-int $index
     */
    public static function isValidIndexOf(int $index, array|Countable $container): void
    {
        Assert::assertTrue(RangeUtil::isValidIndexOfCountable($index, count($container)));
    }

    /**
     * @phpstan-assert array-key $actual
     */
    public static function ofArrayKeyType(mixed $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $dbgCtx->add(['actual type' => get_debug_type($actual)]);
        $dbgCtx->add(compact('actual'));
        Assert::assertTrue(ArrayUtilForTests::isOfArrayKeyType($actual));
    }
}
