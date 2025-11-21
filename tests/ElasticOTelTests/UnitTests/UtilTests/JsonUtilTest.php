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

namespace ElasticOTelTests\UnitTests\UtilTests;

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\TestCaseBase;
use JsonException;

final class JsonUtilTest extends TestCaseBase
{
    /** @noinspection PhpSameParameterValueInspection */
    private static function decode(string $encodedData, bool $asAssocArray): mixed
    {
        $decodedData = json_decode($encodedData, /* associative: */ $asAssocArray);
        if ($decodedData === null && ($encodedData !== 'null')) {
            throw new JsonException(
                'json_decode() failed.'
                . ' json_last_error_msg(): ' . json_last_error_msg() . '.'
                . ' encodedData: `' . $encodedData . '\''
            );
        }
        return $decodedData;
    }

    public function testMapWithNumericKeys(): void
    {
        $original = ['0' => 0];
        $serialized = JsonUtil::encode((object)$original);
        self::assertSame(1, preg_match('/^\s*{\s*"0"\s*:\s*0\s*}\s*$/', $serialized));
        $decodedJson = self::decode($serialized, asAssocArray: true);
        self::assertIsArray($decodedJson);
        AssertEx::equalMaps($original, $decodedJson);
    }
}
