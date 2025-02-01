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

use Countable;
use Elastic\OTel\Util\StaticClassTrait;
use Generator;
use Iterator;
use PHPUnit\Framework\Assert;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IterableUtil
{
    use StaticClassTrait;

    /**
     * @param iterable<mixed> $iterable
     */
    public static function count(iterable $iterable): int
    {
        if ($iterable instanceof Countable) {
            return count($iterable);
        }

        $result = 0;
        foreach ($iterable as $ignored) {
            ++$result;
        }
        return $result;
    }

    /**
     * @param iterable<mixed> $iterable
     */
    public static function isEmpty(iterable $iterable): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($iterable as $ignored) {
            return false;
        }
        return true;
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     * @param T          &$valOut
     * @param-out T       $valOut
     */
    public static function getFirstValue(iterable $iterable, /* out */ mixed &$valOut): bool
    {
        return self::getNthValue($iterable, /* n: */ 0, /* out */ $valOut);
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     * @param T          &$valOut
     * @param-out T       $valOut
     */
    public static function getNthValue(iterable $iterable, int $n, /* out */ mixed &$valOut): bool
    {
        $i = 0;
        foreach ($iterable as $val) {
            if ($i === $n) {
                $valOut = $val;
                return true;
            }
            ++$i;
        }
        return false;
    }

    /**
     * @param iterable<mixed, mixed> $iterable
     *
     * @return iterable<mixed, mixed>
     */
    public static function skipFirst(iterable $iterable): iterable
    {
        $isFirst = true;
        foreach ($iterable as $key => $val) {
            if ($isFirst) {
                $isFirst = false;
                continue;
            }
            yield $key => $val;
        }
    }

    /**
     * @template TValue
     *
     * @param iterable<TValue> $iterable
     *
     * @return array<TValue>
     */
    public static function toList(iterable $iterable): array
    {
        if (is_array($iterable)) {
            return $iterable;
        }

        $result = [];
        foreach ($iterable as $val) {
            $result[] = $val;
        }
        return $result;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $iterable
     *
     * @return array<TKey, TValue>
     *
     * @noinspection PhpUnused
     */
    public static function toMap(iterable $iterable): array
    {
        if (is_array($iterable)) {
            return $iterable;
        }

        /**
         * @var array<TKey, TValue> $result
         */
        $result = [];
        /** @noinspection PhpLoopCanBeConvertedToArrayMapInspection */
        foreach ($iterable as $key => $val) {
            $result[$key] = $val;
        }
        return $result;
    }

    /**
     * @template T
     *
     * @param iterable<T> $inputIterable
     *
     * @return Generator<T>
     */
    public static function iterableToGenerator(iterable $inputIterable): Generator
    {
        foreach ($inputIterable as $val) {
            yield $val;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $inputIterable
     *
     * @return Iterator<T>
     */
    public static function iterableToIterator(iterable $inputIterable): Iterator
    {
        if ($inputIterable instanceof Iterator) {
            return $inputIterable;
        }

        return self::iterableToGenerator($inputIterable);
    }

    /**
     * @param iterable<mixed> $iterables
     *
     * @return Generator<mixed[]>
     *
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    public static function zip(iterable ...$iterables): Generator
    {
        if (ArrayUtilForTests::isEmpty($iterables)) {
            return;
        }

        /** @var Iterator<mixed>[] $iterators */
        $iterators = [];
        foreach ($iterables as $inputIterable) {
            $iterator = self::iterableToIterator($inputIterable);
            $iterator->rewind();
            $iterators[] = $iterator;
        }

        while (true) {
            $tuple = [];
            foreach ($iterators as $iterator) {
                if ($iterator->valid()) {
                    $tuple[] = $iterator->current();
                    $iterator->next();
                } else {
                    Assert::assertTrue(ArrayUtilForTests::isEmpty($tuple));
                }
            }

            if (ArrayUtilForTests::isEmpty($tuple)) {
                return;
            }

            Assert::assertSame(count($iterables), count($tuple));
            yield $tuple;
        }
    }

    /**
     * @template TKey
     *
     * @param iterable<TKey, mixed> $inputMap
     *
     * @return Generator<TKey>
     */
    public static function keys(iterable $inputMap): Generator
    {
        foreach ($inputMap as $key => $_) {
            yield $key;
        }
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $inputSeq
     *
     * @return iterable<TKey, TValue>
     */
    public static function duplicateEachElement(iterable $inputSeq, int $dupCount): iterable
    {
        foreach ($inputSeq as $key => $value) {
            foreach (RangeUtil::generateUpTo($dupCount) as $ignored) {
                yield $key => $value;
            }
        }
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $input1
     * @param iterable<TKey, TValue> $input2
     *
     * @return iterable<TKey, TValue>
     */
    public static function concat(iterable $input1, iterable $input2): iterable
    {
        foreach ($input1 as $key => $value) {
            yield $key => $value;
        }
        foreach ($input2 as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @template TValue
     *
     * @param TValue           $headVal
     * @param iterable<TValue> $tail
     *
     * @return iterable<TValue>
     */
    public static function prepend($headVal, iterable $tail): iterable
    {
        yield $headVal;
        yield from $tail;
    }

    /**
     * @template TValue
     *
     * @param TValue[] $inArray
     * @param int      $suffixStartIndex
     *
     * @return iterable<TValue>
     */
    public static function arraySuffix(array $inArray, int $suffixStartIndex): iterable
    {
        foreach (RangeUtil::generateFromToIncluding($suffixStartIndex, count($inArray) - 1) as $index) {
            yield $inArray[$index];
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     * @param T          &$lastValue
     *
     * @param-out T       $lastValue
     *
     * @noinspection PhpUnused
     */
    public static function getLastValue(iterable $iterable, /* out */ mixed &$lastValue): bool
    {
        $wereThereAnyElements = false;
        foreach ($iterable as $value) {
            $wereThereAnyElements = true;
            $lastValue = $value;
        }
        return $wereThereAnyElements;
    }

    /**
     * @template TInputValue
     * @template TOutputValue
     *
     * @param iterable<TInputValue>               $iterable
     * @param callable(TInputValue): TOutputValue $mapFunc
     *
     * @return iterable<TOutputValue>
     */
    public static function map(iterable $iterable, callable $mapFunc): iterable
    {
        foreach ($iterable as $val) {
            yield $mapFunc($val);
        }
    }

    /**
     * @template TValue of int|float
     *
     * @param iterable<TValue> $iterable
     *
     * @return ?TValue
     */
    public static function max(iterable $iterable): mixed
    {
        /** @var ?TValue $result */
        $result = null;
        foreach ($iterable as $val) {
            if ($result === null || $result < $val) {
                $result = $val;
            }
        }
        return $result;
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return T
     */
    public static function getSingleValue(iterable $iterable): mixed
    {
        $iterator = self::iterableToIterator($iterable);
        $iterator->rewind();
        Assert::assertTrue($iterator->valid());
        $result = $iterator->current();
        $iterator->next();
        Assert::assertFalse($iterator->valid());
        return $result;
    }
}
