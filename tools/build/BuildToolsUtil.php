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

namespace ElasticOTelTools\Build;

use Elastic\OTel\Log\LogLevel;
use JsonException;
use Throwable;

/**
 * @phpstan-type EnvVars array<string, string>
 */
final class BuildToolsUtil
{
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    public const FAILURE_EXIT_CODE = 1;

    /**
     * @param callable(): void $code
     */
    public static function runCmdLineImpl(callable $code): void
    {
        $exitCode = 0;

        try {
            $code();
        } catch (Throwable $throwable) {
            $exitCode = self::FAILURE_EXIT_CODE;
            self::logThrowable(LogLevel::critical, __LINE__, __METHOD__, $throwable);
        }

        exit($exitCode);
    }

    /**
     * @template TCodeRetVal of mixed
     *
     * @param callable(): TCodeRetVal $code
     * @param callable(): void $cleanUp
     *
     * @return TCodeRetVal
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function runCodeAndCleanUp(callable $code, callable $cleanUp): mixed
    {
        $implFinishedSuccessfully = false;
        try {
            $retVal = $code();
            $implFinishedSuccessfully = true;
            return $retVal;
        } finally {
            try {
                $cleanUp();
            } catch (Throwable $throwableFromCleanUp) {
                if ($implFinishedSuccessfully) {
                    throw $throwableFromCleanUp;
                } else {
                    $logLevel = LogLevel::warning;
                    self::logWithLevel($logLevel, __LINE__, __METHOD__, 'Failed to clean up');
                    self::logThrowable($logLevel, __LINE__, __METHOD__, $throwableFromCleanUp);
                }
            }
        }
    }

    public static function isCurrentOsWindows(): bool
    {
        /** @var ?bool $cachedResult */
        $cachedResult = null;

        if ($cachedResult === null) {
            $cachedResult = (strnatcasecmp(PHP_OS_FAMILY, 'Windows') === 0);
        }
        /** @var bool $cachedResult */

        return $cachedResult;
    }

    public static function execShellCommand(string $shellCmd): void
    {
        self::logInfo(__LINE__, __METHOD__, "Executing shell command: $shellCmd");
        $retVal = system($shellCmd, /* out */ $exitCode);
        self::assertNotFalse($retVal, compact('retVal'));
        self::assert($exitCode === 0, '$exitCode === 0' . ' ; shellCmd: ' . $shellCmd . ' ; exitCode: ' . $exitCode . ' ; retVal: ' . $retVal);
    }

    /**
     * @param array<string> $parts
     */
    public static function buildShellCommand(array $parts): string
    {
        $cmd = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $cmd .= ($cmd === '' ? '' : ' ') . $part;
        }

        return $cmd;
    }

    /**
     * @template TCodeRetVal
     *
     * @param callable(string $tempDir): TCodeRetVal $code
     *
     * @phpstan-return TCodeRetVal
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function runCodeOnUniqueNameTempDir(string $tempDirNamePrefix, callable $code): mixed
    {
        $tempDir = BuildToolsFileUtil::createTempDirectoryGenerateUniqueName($tempDirNamePrefix);
        return self::runCodeAndCleanUp(
            function () use ($tempDir, $code): mixed {
                return $code($tempDir);
            },
            cleanUp: fn() => BuildToolsFileUtil::deleteTempDirectory($tempDir)
        );
    }

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
    public static function changeCurrentDirectoryRunCodeAndRestore(string $newCurrentDir, callable $code): void
    {
        $originalCurrentDir = BuildToolsFileUtil::getCurrentDirectory();
        BuildToolsFileUtil::changeCurrentDirectory($newCurrentDir);
        BuildToolsUtil::runCodeAndCleanUp($code, cleanUp: fn() => BuildToolsFileUtil::changeCurrentDirectory($originalCurrentDir));
    }

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
    public static function encodeJson(mixed $data, bool $prettyPrint = false): string
    {
        $options = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES;
        $options |= $prettyPrint ? JSON_PRETTY_PRINT : 0;
        $encodedData = json_encode($data, $options);
        if ($encodedData === false) {
            throw new JsonException(
                'json_encode() failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. data type: ' . get_debug_type($data)
            );
        }
        return $encodedData;
    }

    public static function decodeJson(string $encodedData, bool $asAssocArray): mixed
    {
        $decodedData = json_decode($encodedData, /* assoc: */ $asAssocArray);
        if ($decodedData === null && ($encodedData !== 'null')) {
            throw new JsonException(
                'json_decode() failed.'
                . ' json_last_error_msg(): ' . json_last_error_msg() . '.'
                . ' encodedData: `' . $encodedData . '\''
            );
        }
        return $decodedData;
    }

    /**
     * @return iterable<int>
     */
    private static function generateRange(int $begin, int $end, int $step = 1): iterable
    {
        for ($i = $begin; $i < $end; $i += $step) {
            yield $i;
        }
    }

    /**
     * @param int $count
     *
     * @return iterable<int>
     */
    private static function generateRangeUpTo(int $count): iterable
    {
        return self::generateRange(0, $count);
    }

    /**
     * @return iterable<int>
     */
    public static function iterateOverChars(string $input): iterable
    {
        foreach (self::generateRangeUpTo(strlen($input)) as $i) {
            yield ord($input[$i]);
        }
    }

    /**
     * @param class-string $fqClassName
     */
    private static function splitFqClassName(string $fqClassName, /* out */ string &$namespace, /* out */ string &$shortName): void
    {
        // Check if $fqClassName begin with a back slash(es)
        $firstBackSlashPos = strpos($fqClassName, '\\');
        if ($firstBackSlashPos === false) {
            $namespace = '';
            $shortName = $fqClassName;
            return;
        }
        $firstCanonPos = $firstBackSlashPos === 0 ? 1 : 0;

        $lastBackSlashPos = strrpos($fqClassName, '\\', $firstCanonPos);
        if ($lastBackSlashPos === false) {
            $namespace = '';
            $shortName = substr($fqClassName, $firstCanonPos);
            return;
        }

        $namespace = substr($fqClassName, $firstCanonPos, $lastBackSlashPos - $firstCanonPos);
        $shortName = substr($fqClassName, $lastBackSlashPos + 1);
    }

    /**
     * @param class-string<mixed> $fqClassName
     */
    public static function fqClassNameToShort(string $fqClassName): string
    {
        $namespace = '';
        $shortName = '';
        self::splitFqClassName($fqClassName, /* ref */ $namespace, /* ref */ $shortName);
        return $shortName;
    }

    /**
     * @return list<string>
     *
     * @noinspection PhpUnused
     */
    public static function getCommandLineArgs(): array
    {
        /** @var list<string> $argv */
        global $argv;
        return count($argv) > 1 ? array_slice($argv, 1) : [];
    }

    /**
     * Must be defined in class using BuildToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
