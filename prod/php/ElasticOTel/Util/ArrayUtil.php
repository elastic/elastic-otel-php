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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel\Util;

final class ArrayUtil
{
    use StaticClassTrait;

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey                $key
     * @phpstan-param array<TKey, TValue> $array
     *
     * @param-out TValue                  $valueOut
     *
     * @phpstan-assert-if-true TValue     $valueOut
     */
    public static function getValueIfKeyExists(int|string $key, array $array, /* out */ mixed &$valueOut): bool
    {
        if (!array_key_exists($key, $array)) {
            return false;
        }

        $valueOut = $array[$key];
        return true;
    }

    /**
     * @template TKey of array-key
     * @template TArrayValue
     * @template TFallbackValue
     *
     * @phpstan-param TKey                     $key
     * @phpstan-param array<TKey, TArrayValue> $array
     * @phpstan-param TFallbackValue           $fallbackValue
     *
     * @return TArrayValue|TFallbackValue
     */
    public static function getValueIfKeyExistsElse(string|int $key, array $array, mixed $fallbackValue): mixed
    {
        return array_key_exists($key, $array) ? $array[$key] : $fallbackValue;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey                $key
     * @phpstan-param array<TKey, TValue> $array
     *
     * @param-out TValue                  $valueOut
     *
     * @phpstan-assert-if-true TValue     $valueOut
     */
    public static function removeValue(int|string $key, array $array, /* out */ mixed &$valueOut): bool
    {
        if (!array_key_exists($key, $array)) {
            return false;
        }

        $valueOut = $array[$key];
        unset($array[$key]);
        return true;
    }
}
