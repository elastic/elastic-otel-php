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
use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Override;
use ReturnTypeWillChange;

/**
 * @implements ArrayAccess<string, mixed>
 */
class MixedMap implements LoggableInterface, ArrayAccess
{
    /** @var array<string, mixed> */
    private array $map;

    /**
     * @param array<string, mixed> $initialMap
     */
    public function __construct(array $initialMap = [])
    {
        $this->map = $initialMap;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function assertValidMixedMapArray(array $array): array
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        foreach ($array as $key => $ignored) {
            TestCaseBase::assertIsString($key);
        }
        /**
         * @var array<string, mixed> $array
         */
        return $array;
    }

    /**
     * @param array<array-key, mixed> $from
     */
    public static function getFrom(string $key, array $from): mixed
    {
        TestCaseBase::assertArrayHasKey($key, $from);
        return $from[$key];
    }

    public function get(string $key): mixed
    {
        return self::getFrom($key, $this->map);
    }

    /** @noinspection PhpUnused */
    public function getIfKeyExistsElse(string $key, mixed $fallbackValue): mixed
    {
        return ArrayUtil::getValueIfKeyExistsElse($key, $this->map, $fallbackValue);
    }

    /**
     * @param array<array-key, mixed> $from
     */
    public static function getNullableBoolFrom(string $key, array $from): ?bool
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        $value = self::getFrom($key, $from);
        if ($value !== null) {
            TestCaseBase::assertIsBool($value);
        }
        return $value;
    }

    /**
     * @param array<array-key, mixed> $from
     */
    public static function getBoolFrom(string $key, array $from): bool
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        $value = self::getNullableBoolFrom($key, $from);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getNullableBool(string $key): ?bool
    {
        return self::getNullableBoolFrom($key, $this->map);
    }

    public function getBool(string $key): bool
    {
        return self::getBoolFrom($key, $this->map);
    }

    public function tryToGetBool(string $key): ?bool
    {
        if (!array_key_exists($key, $this->map)) {
            return null;
        }
        return self::getBool($key);
    }

    public function isBoolIsNotSetOrSetToTrue(string $key): bool
    {
        return (($value = self::tryToGetBool($key)) === null) || $value;
    }

    /**
     * @param array<array-key, mixed> $from
     */
    public static function getNullableStringFrom(string $key, array $from): ?string
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        $dbgCtx->add(['from' => $from]);
        $value = self::getFrom($key, $from);
        if ($value !== null) {
            TestCaseBase::assertIsString($value);
        }
        return $value;
    }

    public function getNullableString(string $key): ?string
    {
        return self::getNullableStringFrom($key, $this->map);
    }

    public function getString(string $key): string
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->getNullableString($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getNullableFloat(string $key): ?float
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->get($key);
        if ($value === null || is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return floatval($value);
        }
        $dbgCtx->add(['value type' => DbgUtil::getType($value), 'value' => $value]);
        TestCaseBase::fail('Value is not a float');
    }

    /** @noinspection PhpUnused */
    public function getFloat(string $key): float
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->getNullableFloat($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getNullableInt(string $key): ?int
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->get($key);
        if ($value === null || is_int($value)) {
            return $value;
        }

        $dbgCtx->add(['value type' => DbgUtil::getType($value), 'value' => $value]);
        TestCaseBase::fail('Value is not a int');
    }

    /**
     * @param string $key
     *
     * @return null|positive-int|0
     *
     * @noinspection PhpUnused
     */
    public function getNullablePositiveOrZeroInt(string $key): ?int
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->getNullableInt($key);
        if ($value !== null) {
            TestCaseBase::assertGreaterThanOrEqual(0, $value);
        }
        /** @var null|positive-int|0 $value */
        return $value;
    }

    public function getInt(string $key): int
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->getNullableInt($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    /**
     * @param string $key
     *
     * @return positive-int|0
     *
     * @noinspection PhpUnused
     */
    public function getPositiveOrZeroInt(string $key): int
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->getInt($key);
        TestCaseBase::assertGreaterThanOrEqual(0, $value);
        /** @var positive-int|0 $value */
        return $value;
    }

    /**
     * @return ?array<array-key, mixed>
     */
    public function getNullableArray(string $key): ?array
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->get($key);
        if ($value !== null) {
            TestCaseBase::assertIsArray($value);
        }
        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->getNullableArray($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    /**
     * @template TObj of object
     *
     * @param class-string<TObj> $className
     *
     * @phpstan-return ?TObj
     */
    public function getNullableObject(string $key, string $className): ?object
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], DebugContextForTests::funcArgs()));
        $value = $this->get($key);
        if ($value === null) {
            return null;
        }
        TestCaseBase::assertInstanceOf($className, $value);
        return $value;
    }

    /**
     * @template TObj of object
     *
     * @param class-string<TObj> $className
     *
     * @phpstan-return TObj
     */
    public function getObject(string $key, string $className): object
    {
        $value = $this->getNullableObject($key, $className);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getLogLevel(string $key): LogLevel
    {
        return $this->getObject($key, LogLevel::class);
    }

    /**
     * @return self
     */
    public function clone(): self
    {
        return new MixedMap($this->map);
    }

    /**
     * @return array<string, mixed>
     *
     * @noinspection PhpUnused
     */
    public function cloneAsArray(): array
    {
        return $this->map;
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     *
     * @return bool
     */
    #[Override]
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->map);
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->map[$offset];
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     */
    #[Override]
    public function offsetSet($offset, mixed $value): void
    {
        TestCaseBase::assertIsString($offset); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        $this->map[$offset] = $value;
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     */
    #[Override]
    public function offsetUnset($offset): void
    {
        TestCaseBase::assertArrayHasKey($offset, $this->map);
        unset($this->map[$offset]);
    }

    #[Override]
    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->map);
    }
}
