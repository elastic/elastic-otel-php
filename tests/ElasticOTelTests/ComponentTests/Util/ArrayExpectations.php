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

namespace ElasticOTelTests\ComponentTests\Util;

use Countable;
use ElasticOTelTests\Util\ArrayReadInterface;
use ElasticOTelTests\Util\DebugContext;
use Override;
use PHPUnit\Framework\Assert;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-type ArrayLike array<TKey, TValue>|(ArrayReadInterface<TKey, TValue> & Countable)
 */
class ArrayExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /**
     * @param array<TKey, TValue> $expectedArray
     */
    public function __construct(
        public readonly array $expectedArray,
        public readonly bool $allowOtherKeysInActual = true,
    ) {
    }

    /**
     * @phpstan-param TKey   $key
     * @phpstan-param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function add(string|int $key, mixed $value): self
    {
        $newExpectedArray = $this->expectedArray;
        $newExpectedArray[$key] = $value;
        return new self($newExpectedArray, $this->allowOtherKeysInActual);
    }

    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        Assert::assertTrue(is_array($actual) || $actual instanceof ArrayReadInterface);
        /** @var ArrayLike $actual */
        $this->assertMatches($actual);
    }

    /**
     * @param ArrayLike $actual
     */
    public function assertMatches(array|ArrayReadInterface $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        if (!$this->allowOtherKeysInActual) {
            Assert::assertCount(count($this->expectedArray), $actual);
        }

        $dbgCtx->pushSubScope();
        foreach ($this->expectedArray as $expectedKey => $expectedValue) {
            $dbgCtx->resetTopSubScope(compact('expectedKey', 'expectedValue'));
            self::keyExists($expectedKey, $this->expectedArray);
            $actualValue = self::getValue($expectedKey, $actual);
            $dbgCtx->add(compact('actualValue'));
            $this->assertValueMatches($expectedKey, $expectedValue, $actualValue);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @phpstan-param TKey      $key
     * @phpstan-param ArrayLike $array
     */
    private static function keyExists(string|int $key, array|ArrayReadInterface $array): bool
    {
        return is_array($array) ? array_key_exists($key, $array) : $array->keyExists($key);
    }

    /**
     * @phpstan-param TKey      $key
     * @phpstan-param ArrayLike $array
     *
     * @return TValue
     */
    private static function getValue(string|int $key, array|ArrayReadInterface $array): mixed
    {
        if (is_array($array)) {
            Assert::assertArrayHasKey($key, $array);
            return $array[$key];
        }
        return $array->getValue($key);
    }

    /**
     * @phpstan-param TKey   $key
     * @phpstan-param TValue $expectedValue
     * @phpstan-param TValue $actualValue
     */
    protected function assertValueMatches(string|int $key, mixed $expectedValue, mixed $actualValue): void
    {
        Assert::assertSame($expectedValue, $actualValue);
    }
}
