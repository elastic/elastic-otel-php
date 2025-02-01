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
use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTests\Util\ArrayReadInterface;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTests\Util\TextUtilForTests;
use Google\Protobuf\Internal\RepeatedField as ProtobufRepeatedField;
use Opentelemetry\Proto\Common\V1\KeyValue as OTelProtoKeyValue;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-type AttributeValue array<int>|array<mixed>|bool|float|int|null|string
 *
 * @implements ArrayReadInterface<string, AttributeValue>
 */
final class SpanAttributes implements ArrayReadInterface, Countable, LoggableInterface
{
    /** @var array<string, AttributeValue> $keyToValueMap */
    private readonly array $keyToValueMap;

    public function __construct(ProtobufRepeatedField $protobufRepeatedField)
    {
        $this->keyToValueMap = self::convertProtobufRepeatedFieldToMap($protobufRepeatedField);
    }

    /**
     * @return AttributeValue
     */
    private static function extractValue(OTelProtoKeyValue $keyValue): array|bool|float|int|null|string
    {
        if (!$keyValue->hasValue()) {
            return null;
        }

        $anyValue = $keyValue->getValue();
        if ($anyValue === null) {
            return null;
        }

        if ($anyValue->hasArrayValue()) {
            $arrayValue = $anyValue->getArrayValue();
            if ($arrayValue === null) {
                return null;
            }
            $result = [];
            foreach ($arrayValue->getValues() as $repeatedFieldSubValue) {
                $result[] = $repeatedFieldSubValue;
            }
            return $result;
        }

        if ($anyValue->hasBoolValue()) {
            return $anyValue->getBoolValue();
        }

        if ($anyValue->hasBytesValue()) {
            return IterableUtil::toList(TextUtilForTests::iterateOverChars($anyValue->getBytesValue()));
        }

        if ($anyValue->hasDoubleValue()) {
            return $anyValue->getDoubleValue();
        }

        if ($anyValue->hasIntValue()) {
            $value = $anyValue->getIntValue();
            if (is_int($value)) {
                return $value;
            }
            return AssertEx::stringIsIntAndReturn($value);
        }

        if ($anyValue->hasKvlistValue()) {
            $kvListValue = $anyValue->getKvlistValue();
            if ($kvListValue === null) {
                return null;
            }
            $result = [];
            foreach ($kvListValue->getValues() as $repeatedFieldSubKey => $repeatedFieldSubValue) {
                Assert::assertTrue(is_int($repeatedFieldSubKey) || is_string($repeatedFieldSubKey));
                Assert::assertArrayNotHasKey($repeatedFieldSubKey, $result);
                $result[$repeatedFieldSubKey] = $repeatedFieldSubValue;
            }
            return $result;
        }

        if ($anyValue->hasStringValue()) {
            return $anyValue->getStringValue();
        }

        Assert::fail('Unknown value type; ' . LoggableToString::convert(compact('keyValue')));
    }

    /**
     * @param ProtobufRepeatedField $protobufRepeatedField
     *
     * @return array<string, AttributeValue>
     */
    private static function convertProtobufRepeatedFieldToMap(ProtobufRepeatedField $protobufRepeatedField): array
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        $result = [];
        $dbgCtx->pushSubScope();
        foreach ($protobufRepeatedField as $keyValue) {
            $dbgCtx->clearCurrentSubScope(compact('keyValue'));
            Assert::assertInstanceOf(OTelProtoKeyValue::class, $keyValue);
            Assert::assertArrayNotHasKey($keyValue->getKey(), $result);
            $result[$keyValue->getKey()] = self::extractValue($keyValue);
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pop();
        return $result;
    }

    public function keyExists(int|string $key): bool
    {
        return array_key_exists($key, $this->keyToValueMap);
    }

    public function getValue(int|string $key): mixed
    {
        Assert::assertIsString($key);
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists($key, $this->keyToValueMap, /* out */ $attributeValue));
        return $attributeValue;
    }

    public function count(): int
    {
        return count($this->keyToValueMap);
    }

    public function tryToGetBool(string $attributeName): ?bool
    {
        if (!ArrayUtil::getValueIfKeyExists($attributeName, $this->keyToValueMap, /* out */ $attributeValue)) {
            return null;
        }
        Assert::assertIsBool($attributeValue);
        return $attributeValue;
    }

    public function tryToGetFloat(string $attributeName): ?float
    {
        if (!ArrayUtil::getValueIfKeyExists($attributeName, $this->keyToValueMap, /* out */ $attributeValue)) {
            return null;
        }
        TestCaseBase::assertIsFloat($attributeValue);
        return $attributeValue;
    }

    public function tryToGetInt(string $attributeName): ?int
    {
        if (!ArrayUtil::getValueIfKeyExists($attributeName, $this->keyToValueMap, /* out */ $attributeValue)) {
            return null;
        }
        Assert::assertIsInt($attributeValue);
        return $attributeValue;
    }

    public function tryToGetString(string $attributeName): ?string
    {
        if (!ArrayUtil::getValueIfKeyExists($attributeName, $this->keyToValueMap, /* out */ $attributeValue)) {
            return null;
        }
        Assert::assertIsString($attributeValue);
        return $attributeValue;
    }

    public function getBool(string $attributeName): bool
    {
        return AssertEx::notNullAndReturn($this->tryToGetBool($attributeName));
    }

    /** @noinspection PhpUnused */
    public function getFloat(string $attributeName): float
    {
        return AssertEx::notNullAndReturn($this->tryToGetFloat($attributeName));
    }

    public function getInt(string $attributeName): int
    {
        return AssertEx::notNullAndReturn($this->tryToGetInt($attributeName));
    }

    public function getString(string $attributeName): string
    {
        return AssertEx::notNullAndReturn($this->tryToGetString($attributeName));
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->keyToValueMap);
    }
}
