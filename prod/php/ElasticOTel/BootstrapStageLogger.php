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

namespace Elastic\OTel;

use Throwable;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BootstrapStageLogger
{
    public const LOG_CATEGORY = 'Bootstrap';

    public const LEVEL_OFF = 0;
    public const LEVEL_CRITICAL = 1;
    public const LEVEL_ERROR = 2;
    public const LEVEL_WARNING = 3;
    public const LEVEL_INFO = 4;
    public const LEVEL_DEBUG = 5;
    public const LEVEL_TRACE = 6;

    private const LEVEL_AS_STRING = [
        self::LEVEL_OFF => 'OFF',
        self::LEVEL_CRITICAL => 'CRITICAL',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_TRACE => 'TRACE',
    ];

    private static int $maxEnabledLevel = self::LEVEL_OFF;

    private static string $phpSrcCodePathPrefixToRemove;
    private static string $classNamePrefixToRemove;

    private static ?int $pid = null;

    private static ?bool $isStderrDefined = null;

    private static function levelToString(int $level): string
    {
        if (array_key_exists($level, self::LEVEL_AS_STRING)) {
            return self::LEVEL_AS_STRING[$level];
        }

        return "LEVEL ($level)";
    }

    private static function ensureStdErrIsDefined(): bool
    {
        if (self::$isStderrDefined === null) {
            if (defined('STDERR')) {
                self::$isStderrDefined = true;
            } else {
                define('STDERR', fopen('php://stderr', 'w'));
                self::$isStderrDefined = defined('STDERR');
            }
        }

        return self::$isStderrDefined;
    }

    /** @noinspection PhpUnused */
    public static function writeLineToStdErr(string $text): void
    {
        if (self::ensureStdErrIsDefined()) {
            fwrite(STDERR, $text . PHP_EOL);
        }
    }

    public static function nullableToLog(mixed $str): mixed
    {
        return $str === null ? 'null' : $str;
    }

    public static function configure(int $maxEnabledLevel, string $phpSrcCodeRootDir, string $rootNamespace): void
    {
        self::$maxEnabledLevel = $maxEnabledLevel;
        if(is_int($pid = getmypid())) {
            self::$pid = $pid;
        }

        self::$phpSrcCodePathPrefixToRemove = $phpSrcCodeRootDir . DIRECTORY_SEPARATOR;
        self::$classNamePrefixToRemove = $rootNamespace . '\\';

        self::logDebug(
            'Exiting...'
            . '; maxEnabledLevel: ' . self::levelToString($maxEnabledLevel)
            . '; phpSrcCodePathPrefixToRemove: ' . self::$phpSrcCodePathPrefixToRemove
            . '; classNamePrefixToRemove: ' . self::$classNamePrefixToRemove
            . '; maxEnabledLevel: ' . self::levelToString($maxEnabledLevel)
            . '; pid: ' . self::nullableToLog(self::$pid),
            __FILE__, __LINE__, __CLASS__, __FUNCTION__
        );
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logCritical(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_CRITICAL, $message, $file, $line, $class, $func);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logError(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_ERROR, $message, $file, $line, $class, $func);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logWarning(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_WARNING, $message, $file, $line, $class, $func);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logInfo(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_INFO, $message, $file, $line, $class, $func);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logDebug(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_DEBUG, $message, $file, $line, $class, $func);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function logTrace(string $message, string $file, int $line, string $class, string $func): void
    {
        self::logWithLevel(self::LEVEL_TRACE, $message, $file, $line, $class, $func);
    }

    public static function isEnabledForLevel(int $statementLevel): bool
    {
        return $statementLevel <= self::$maxEnabledLevel;
    }

    public static function logCriticalThrowable(Throwable $throwable, string $message, string $file, int $line, string $class, string $func): void
    {
        self::logCritical(
            $message . '.'
            . ' ' . get_class($throwable) . ': ' . $throwable->getMessage()
            . PHP_EOL . 'Stack trace:' . PHP_EOL . $throwable->getTraceAsString(),
            $file, $line, $class, $func
        );
    }

    private static function isPrefixOf(string $prefix, string $text, bool $isCaseSensitive = true): bool
    {
        $prefixLen = strlen($prefix);
        if ($prefixLen === 0) {
            return true;
        }

        return substr_compare(
                   $text /* <- haystack */,
                   $prefix /* <- needle */,
                   0 /* <- offset */,
                   $prefixLen /* <- length */,
                   !$isCaseSensitive /* <- case_insensitivity */
               ) === 0;
    }

    private static function processSourceCodeFilePathForLog(string $file): string
    {
        return
            self::isPrefixOf(self::$phpSrcCodePathPrefixToRemove, $file, /* isCaseSensitive: */ false)
                ? substr($file, strlen(self::$phpSrcCodePathPrefixToRemove))
                : $file;
    }

    private static function processClassNameForLog(string $class): string
    {
        return
            self::isPrefixOf(self::$classNamePrefixToRemove, $class, /* isCaseSensitive: */ false)
                ? substr($class, strlen(self::$classNamePrefixToRemove))
                : $class;
    }

    private static function processClassFunctionNameForLog(string $class, string $func): string
    {
        if ($class === '') {
            return $func;
        }
        return self::processClassNameForLog($class) . '::' . $func;
    }

    private static function logWithLevel(int $statementLevel, string $message, string $file, int $line, string $class, string $func): void
    {
        if (!self::isEnabledForLevel($statementLevel)) {
            return;
        }

        /**
         * elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        \elastic_otel_log(
            0 /* $isForced */,
            $statementLevel,
            self::LOG_CATEGORY,
            self::processSourceCodeFilePathForLog($file),
            $line,
            self::processClassFunctionNameForLog($class, $func),
            $message
        );
    }

    /**
     * @noinspection PhpUnused
     */
    public static function possiblySecuritySensitive(mixed $value): mixed
    {
        return self::isEnabledForLevel(self::LEVEL_TRACE) ? $value : 'REDACTED (POSSIBLY SECURITY SENSITIVE) DATA';
    }
}
