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

use Elastic\OTel\Util\ArrayUtil;
use PHPUnit\Framework\Assert;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template TKey of array-key
 * @template TValue of mixed
 */
final class LimitedSizeCache
{
    /** @var array<TKey, TValue> */
    private array $keyToValue = [];

    /**
     * @param non-negative-int $countLowWaterMark
     * @param non-negative-int $countHighWaterMark
     */
    public function __construct(
        private readonly int $countLowWaterMark,
        private readonly int $countHighWaterMark,
    ) {
        Assert::assertGreaterThan($countLowWaterMark, $countHighWaterMark);
    }

    /**
     * @param TKey   $key
     * @param TValue $value
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function put(string|int $key, mixed $value): void
    {
        $cacheCount = count($this->keyToValue);
        if ($cacheCount > $this->countHighWaterMark) {
            // Keep the last countLowWaterMark entries
            $this->keyToValue = array_slice(array: $this->keyToValue, offset: $cacheCount - $this->countLowWaterMark);
        }

        // Remove the key if it already exists  to make the new entry the last in added order
        if (array_key_exists($key, $this->keyToValue)) {
            unset($this->keyToValue[$key]);
        }
        $this->keyToValue[$key] = $value;
    }

    /**
     * @param TKey                   $key
     * @param callable(TKey): TValue $computeValue
     *
     * @return TValue
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function getIfCachedElseCompute(string|int $key, callable $computeValue): mixed
    {
        if (ArrayUtil::getValueIfKeyExists($key, $this->keyToValue, /* out */ $valueInCache)) {
            return $valueInCache;
        }

        $value = $computeValue($key);
        $this->put($key, $value);
        return $value;
    }
}
