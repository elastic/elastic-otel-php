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

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\JsonUtil;
use PHPUnit\Framework\Assert;

final class PhpSerializationUtil
{
    use StaticClassTrait;

    private const CHECKSUM_KEY = 'checksum';
    private const DATA_KEY = 'data';

    public static function serializeToString(mixed $val): string
    {
        Assert::assertTrue(extension_loaded('zlib'));
        $serialized = serialize($val);
        Assert::assertNotFalse($compressed = gzcompress($serialized, level: 9 /* 9 for maximum compression */));
        $data = base64_encode($compressed);
        $checksum = crc32($data);
        return JsonUtil::encode([self::CHECKSUM_KEY => $checksum, self::DATA_KEY => $data]);
    }

    public static function unserializeFromString(string $serialized): mixed
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $decodedJson = JsonUtil::decode($serialized);
        $dbgCtx->add(compact('decodedJson'));
        Assert::assertIsArray($decodedJson);
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists(self::CHECKSUM_KEY, $decodedJson, /* out */ $receivedChecksum));
        $dbgCtx->add(compact('receivedChecksum'));
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists(self::DATA_KEY, $decodedJson, /* out */ $data));
        $dbgCtx->add(compact('data'));
        Assert::assertIsString($data);
        Assert::assertSame($receivedChecksum, crc32($data));
        Assert::assertNotFalse($compressed = base64_decode($data, strict: true));
        Assert::assertNotFalse($serialized = gzuncompress($compressed));
        return unserialize($serialized);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @phpstan-return T
     */
    public static function unserializeFromStringAssertType(string $serialized, string $className): object
    {
        Assert::assertTrue(class_exists($className));
        $obj = self::unserializeFromString($serialized);
        Assert::assertInstanceOf($className, $obj);
        return $obj;
    }
}
