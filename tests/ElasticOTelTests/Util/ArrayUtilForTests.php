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

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\Log\LoggableToString;
use PHPUnit\Framework\Assert;

final class ArrayUtilForTests
{
    use StaticClassTrait;

    /**
     * @param array<array-key, mixed> $array
     */
    public static function isEmpty(array $array): bool
    {
        return count($array) === 0;
    }

    /**
     * @template T
     * @param    T[] $array
     * @return   T
     */
    public static function getFirstValue(array $array): mixed
    {
        return $array[array_key_first($array)];
    }

    /**
     * @template T
     * @param    T[] $array
     * @return   T
     */
    public static function getSingleValue(array $array): mixed
    {
        Assert::assertCount(1, $array);
        return self::getFirstValue($array);
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $array
     *
     * @return  T
     */
    public static function &getLastValue(array $array)
    {
        Assert::assertNotEmpty($array);
        return $array[array_key_last($array)];
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey $key
     * @param TValue $value
     * @param array<TKey, TValue> &$result
     */
    public static function addUnique(string|int $key, mixed $value, array &$result): void
    {
        Assert::assertArrayNotHasKey($key, $result, LoggableToString::convert(['key' => $key, 'value' => $value, 'result' => $result]));
        $result[$key] = $value;
    }

    /**
     * @template        T
     * @phpstan-param   iterable<T> $haystack
     * @phpstan-param   callable $predicate
     * @phpstan-param   T $default
     * @phpstan-return  T|null
     *
     * @param iterable $haystack
     * @param callable $predicate
     * @param null     $default
     *
     * @return mixed
     *
     * @noinspection PhpUnused
     */
    public static function findByPredicate(iterable $haystack, callable $predicate, $default = null)
    {
        foreach ($haystack as $value) {
            if ($predicate($value)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @template TKey of string|int
     * @template TValue
     *
     * @param array<TKey, TValue> $from
     * @param array<TKey, TValue> $to
     */
    public static function append(array $from, /* in,out */ array &$to): void
    {
        $to = array_merge($to, $from);
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param array<TKey, TValue> $map
     * @param TKey                $keyToFind
     *
     * @return  int
     *
     * @noinspection PhpUnused
     */
    public static function getAdditionOrderIndex(array $map, $keyToFind): int
    {
        $additionOrderIndex = 0;
        foreach ($map as $key => $ignored) {
            if ($key === $keyToFind) {
                return $additionOrderIndex;
            }
            ++$additionOrderIndex;
        }
        Assert::fail('Not found key in map; ' . LoggableToString::convert(['keyToFind' => $keyToFind, 'map' => $map]));
    }

    /**
     * @param array<string, mixed> $argsMap
     */
    public static function getFromMap(string $argKey, array $argsMap): mixed
    {
        Assert::assertArrayHasKey($argKey, $argsMap);
        return $argsMap[$argKey];
    }

    /**
     * @param string               $argKey
     * @param array<string, mixed> $argsMap
     *
     * @return bool
     *
     * @noinspection PhpUnused
     */
    public static function getBoolFromMap(string $argKey, array $argsMap): bool
    {
        $val = self::getFromMap($argKey, $argsMap);
        Assert::assertIsBool($val, LoggableToString::convert(['argKey' => $argKey, 'argsMap' => $argsMap]));
        return $val;
    }

    /**
     * @template TValue
     *
     * @param TValue   $value
     * @param TValue[] $list
     *
     * @noinspection PhpUnused
     */
    public static function addToListIfNotAlreadyPresent($value, array &$list, bool $shouldUseStrictEq = true): void
    {
        if (!in_array($value, $list, $shouldUseStrictEq)) {
            $list[] = $value;
        }
    }

    /**
     * @template TValue
     * *
     * @param array<array-key, TValue> &$removeFromArray
     * @param TValue                    $valueToRemove
     */
    public static function removeFirstByValue(/* in,out */ array &$removeFromArray, mixed $valueToRemove): bool
    {
        foreach ($removeFromArray as $key => $value) {
            if ($value === $valueToRemove) {
                unset($removeFromArray[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * @template TKey of array-key
     * *
     * @param array<TKey, mixed> &$removeFromArray
     *
     * @phpstan-param TKey        $keyToRemove
     *
     * @noinspection PhpUnused
     */
    public static function removeByKey(/* in,out */ array &$removeFromArray, string|int $keyToRemove): bool
    {
        if (array_key_exists($keyToRemove, $removeFromArray)) {
            unset($removeFromArray[$keyToRemove]);
            return true;
        }
        return false;
    }

    /**
     * @template TValue
     * *
     * @param array<array-key, TValue> &$removeFromArray
     * @param array<array-key, TValue>  $valuesToRemove
     */
    public static function removeAllValues(/* in,out */ array &$removeFromArray, array $valuesToRemove): int
    {
        $countRemoved = 0;
        foreach ($removeFromArray as $key => $value) {
            if (in_array($value, $valuesToRemove, strict: true)) {
                unset($removeFromArray[$key]);
                ++$countRemoved;
            }
        }
        return $countRemoved;
    }

    /**
     * @template TValue
     *
     * @param TValue[] $array
     *
     * @return iterable<TValue>
     */
    public static function iterateListInReverse(array $array): iterable
    {
        AssertEx::arrayIsList($array);
        for ($currentValue = end($array); key($array) !== null; $currentValue = prev($array)) {
            yield $currentValue; // @phpstan-ignore generator.valueType
        }
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param array<TKey, TValue> $array
     *
     * @return iterable<TKey, TValue>
     */
    public static function iterateMapInReverse(array $array): iterable
    {
        for ($currentValue = end($array); ($currentKey = key($array)) !== null; $currentValue = prev($array)) {
            yield $currentKey => $currentValue; // @phpstan-ignore generator.valueType
        }
    }
}
