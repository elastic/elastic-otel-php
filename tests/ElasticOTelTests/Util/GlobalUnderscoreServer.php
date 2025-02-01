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
use Elastic\OTel\Util\StaticClassTrait;
use Elastic\OTel\Util\TextUtil;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class GlobalUnderscoreServer
{
    use StaticClassTrait;

    private const HTTP_REQUEST_HEADER_KEY_PREFIX = 'HTTP_';

    private static function getValue(string $key): mixed
    {
        TestCaseBase::assertArrayHasKey($key, $_SERVER);
        return $_SERVER[$key];
    }

    public static function requestMethod(): string
    {
        return TestCaseBase::assertIsStringAndReturn(self::getValue('REQUEST_METHOD'));
    }

    public static function requestUri(): string
    {
        return TestCaseBase::assertIsStringAndReturn(self::getValue('REQUEST_URI'));
    }

    public static function getRequestHeaderValue(string $headerName): ?string
    {
        if (ArrayUtil::getValueIfKeyExists(self::HTTP_REQUEST_HEADER_KEY_PREFIX . strtoupper($headerName), $_SERVER, /* out */ $headerValue)) {
            TestCaseBase::assertIsString($headerValue);
            return $headerValue;
        }
        return null;
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function getAll(): iterable
    {
        foreach ($_SERVER as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @return iterable<string, mixed>
     *
     * @noinspection PhpUnused
     */
    public static function getAllRequestHeaders(): iterable
    {
        $prefixLen = strlen(self::HTTP_REQUEST_HEADER_KEY_PREFIX);
        foreach ($_SERVER as $key => $value) {
            if (TextUtil::isPrefixOf(self::HTTP_REQUEST_HEADER_KEY_PREFIX, $key)) {
                yield substr($key, $prefixLen) => $value;
            }
        }
    }
}
