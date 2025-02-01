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

namespace ElasticOTelTests\substitutes;

use RuntimeException;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SubstitutesUtil
{
    private static function appendStackTraceToMessage(string $msg): string
    {
        return $msg . '; stack trace: ' . json_encode(debug_backtrace(), flags: JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    }

    /**
     * @param class-string<object> $className
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function assertClassNotLoaded(string $className, bool $autoload): void
    {
        if (class_exists($className, $autoload)) {
            throw new RuntimeException(self::appendStackTraceToMessage('Class ' . $className . ' IS loaded'));
        }
    }

    /**
     * @param class-string<object> $className
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function assertClassLoaded(string $className, bool $autoload): void
    {
        if (!class_exists($className, $autoload)) {
            throw new RuntimeException(self::appendStackTraceToMessage('Class ' . $className . ' is NOT loaded'));
        }
    }

    /**
     * @param class-string<object> $className
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function assertClassHasProperty(string $className, string $propertyName): void
    {
        if (!property_exists($className, $propertyName)) {
            throw new RuntimeException(self::appendStackTraceToMessage('Class ' . $className . ' does have property ' . $propertyName));
        }
    }
}
