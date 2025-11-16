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

namespace ElasticOTelTools;

use Countable;
use RuntimeException;

trait ToolsAssertTrait
{
    /**
     * @phpstan-assert true $condition
     */
    public static function assert(bool $condition, string $message): void
    {
        if ($condition) {
            return;
        }

        self::assertFail('Assertion failed: ' . $message);
    }

    public static function assertFail(string $message): never
    {
        throw new RuntimeException('Assertion failed: ' . $message);
    }

    /**
     * @param ?array<string, mixed> $dbgCtx1
     * @param ?array<string, mixed> $dbgCtx2
     */
    private static function convertAssertDbgCtxToStringToAppend(?array $dbgCtx1, ?array $dbgCtx2 = null): string
    {
        $dbgCtx = [];
        if ($dbgCtx1 != null) {
            $dbgCtx += $dbgCtx1;
        }
        if ($dbgCtx2 != null) {
            $dbgCtx += $dbgCtx2;
        }
        return empty($dbgCtx) ? '' : (' ; ' . json_encode($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert null $actual
     *
     * @phpstan-return null
     */
    public static function assertNull(mixed $actual, ?array $dbgCtx = null): mixed
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert($actual === null, "$dbgName === null ; get_debug_type($dbgName): " . get_debug_type($actual) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @template T
     *
     * @phpstan-param ?T $actual
     * @phpstan-param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-return T
     *
     * @phpstan-assert !null $actual
     */
    public static function assertNotNull(mixed $actual, ?array $dbgCtx = null): mixed
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert($actual !== null, "$dbgName !== null" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertSame(mixed $expected, mixed $actual, ?array $dbgCtx = null): void
    {
        self::assert($expected === $actual, '$expected === $actual' . self::convertAssertDbgCtxToStringToAppend(compact('expected', 'actual'), $dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert array<array-key, mixed> $actual
     *
     * @phpstan-return array<array-key, mixed>
     */
    public static function assertIsArray(mixed $actual, ?array $dbgCtx = null): array
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(is_array($actual), "is_array($dbgName) ; get_debug_type($dbgName): " . get_debug_type($actual) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert list<mixed> $actual
     *
     * @phpstan-return list<mixed>
     *
     * @noinspection PhpUnused
     */
    public static function assertIsList(mixed $actual, ?array $dbgCtx = null): array
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assertIsArray($actual, $dbgCtx);
        self::assert(array_is_list($actual), "array_is_list($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param Countable|array<mixed> $actual
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertCount(int $expectedCount, Countable|array $actual, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(count($actual) === $expectedCount, "count($dbgName) === $expectedCount" . self::convertAssertDbgCtxToStringToAppend(compact('expectedCount'), $dbgCtx));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @phpstan-param TKey $expectedKey
     * @phpstan-param array<TKey, TValue> $actualArray
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-return TValue
     *
     * @phpstan-assert array{key: mixed, ...} $actualArray
     */
    public static function assertArrayHasKey(string|int $expectedKey, array $actualArray, ?array $dbgCtx = null): mixed
    {
        $actualKeys = array_keys($actualArray);
        self::assert(array_key_exists($expectedKey, $actualArray), 'array_key_exists($key, $array)' . self::convertAssertDbgCtxToStringToAppend(compact('expectedKey', 'actualKeys'), $dbgCtx));
        return $actualArray[$expectedKey];
    }

    /**
     * @param array-key $key
     * @param array<array-key, mixed> $array
     * @param ?array<string, mixed> $dbgCtx
     *
     * @noinspection PhpUnused
     */
    public static function assertArrayNotHasKey(mixed $key, array $array, ?array $dbgCtx = null): void
    {
        self::assert(!array_key_exists($key, $array), '!array_key_exists($key, $array)' . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert int $actual
     *
     * @phpstan-return int
     *
     * @noinspection PhpUnused
     */
    public static function assertIsInt(mixed $actual, ?array $dbgCtx = null): int
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(is_int($actual), "is_int($dbgName) ; get_debug_type($dbgName): " . get_debug_type($actual) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert string $actual
     *
     * @phpstan-return string
     */
    public static function assertIsString(mixed $actual, ?array $dbgCtx = null): string
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(is_string($actual), "is_string($dbgName) ; get_debug_type($dbgName): " . get_debug_type($actual) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert non-empty-string $actual
     *
     * @phpstan-return non-empty-string
     */
    public static function assertStringNotEmpty(string $actual, ?array $dbgCtx = null): string
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert($actual !== '', "$dbgName !== ''" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $actual
     * @param ?array<string, mixed> $dbgCtx
     *
     * @return non-empty-array<TKey, TValue>
     *
     * @phpstan-assert non-empty-array<TKey, TValue> $actual
     *
     * @noinspection PhpUnused
     */
    public static function assertArrayNotEmpty(array $actual, ?array $dbgCtx = null): array
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(count($actual) !== 0, "count($dbgName) !== 0" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @template TValue
     * *
     * @param TValue|false $actual
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert !false $actual
     * @phpstan-assert TValue $actual
     *
     * @phpstan-return TValue
     */
    public static function assertNotFalse(mixed $actual, ?array $dbgCtx = null): mixed
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert($actual !== false, "$dbgName !== false" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $actual;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertFileExists(string $filePath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(file_exists($filePath), "file_exists($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @noinspection PhpUnused
     */
    public static function assertFileDoesNotExist(string $filePath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(!file_exists($filePath), "!file_exists($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertDirectoryExists(string $dirPath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? 'actual' : array_key_first($dbgCtx);
        self::assert(is_dir($dirPath), "file_exists($dbgName) && is_dir($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertFilesHaveSameContent(string $file1, string $file2, ?array $dbgCtx = null): void
    {
        $file1Contents = ToolsUtil::getFileContents($file1);
        $file2Contents = ToolsUtil::getFileContents($file2);
        self::assert(
            $file1Contents === $file2Contents,
            '$file1Contents == $file1Content2'
            . self::convertAssertDbgCtxToStringToAppend(compact('file1', 'file2', 'file1Contents', 'file2Contents'), $dbgCtx)
        );
    }
}
