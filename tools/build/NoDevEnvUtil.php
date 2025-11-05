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

use Countable;
use DirectoryIterator;
use Elastic\OTel\Util\BoolUtil;
use RuntimeException;
use Throwable;

final class NoDevEnvUtil
{
    public const ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND';

    private const KEEP_TEMP_FILES_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_DEV_KEEP_TEMP_FILES';
    private const KEEP_TEMP_FILES_DEFAULT_VALUE = false;

    public const COMPOSER_JSON_FILE_NAME = 'composer.json';

    private const COMPOSER_INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS =
        '--ignore-platform-req=ext-mysqli'
        . ' '
        . '--ignore-platform-req=ext-pgsql'
        . ' '
        . '--ignore-platform-req=ext-opentelemetry'
    ;

    public const COMPOSER_JSON_REQUIRE_KEY = 'require';
    public const COMPOSER_JSON_REQUIRE_DEV_KEY = 'require-dev';

    /**
     * @see elastic_otel_php_build_tools_composer_lock_files_dir in tool/shared.sh
     */
    private const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    public const DEV_ENV_KIND = 'dev';
    public const PROD_ENV_KIND = 'prod';
    public const TESTS_ENV_KIND = 'tests';

    public const COMPOSER_ENV_VAR_NAME = 'COMPOSER';

    private static ?bool $isStderrDefined = null;

    public static function logThrowable(Throwable $throwable): void
    {
        $getTraceEntryProp = function (array $traceEntry, string $propKey, string $defaultValue): string {
            if (!array_key_exists($propKey, $traceEntry)) {
                return $defaultValue;
            }
            $propVal = $traceEntry[$propKey];
            return is_scalar($propVal) ? strval($propVal) : $defaultValue;
        };
        self::log('Message: ' . $throwable->getMessage());
        self::log('Stack trace:');
        foreach ($throwable->getTrace() as $traceEntry) {
            $text = $getTraceEntryProp($traceEntry, 'file', '<FILE>') . ':' . $getTraceEntryProp($traceEntry, 'line', '<LINE>');
            $text .= ' (' . $getTraceEntryProp($traceEntry, 'class', '<CLASS>') . '::' . $getTraceEntryProp($traceEntry, 'function', '<FUNC>') . ')';
            self::log("\t" . $text);
        }
    }

    public static function realPath(string $path): string
    {
        $retVal = realpath($path);
        self::assertNotFalse($retVal, compact('retVal', 'path'));
        return $retVal;
    }

    public static function getFirstDirFromUnixPath(string $path): string
    {
        $slashPos = strpos($path, '/');
        if ($slashPos === false) {
            return $path;
        }
        if ($slashPos === 0) {
            return '/';
        }

        return substr($path, offset: 0, length: $slashPos - 1);
    }

    public static function copyFile(string $fromFilePath, string $toFilePath): void
    {
        self::log("Copying file $fromFilePath to $toFilePath");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("copy \"$fromFilePath\" \"$toFilePath\"");
        } else {
            self::execShellCommand("cp \"$fromFilePath\" \"$toFilePath\"");
        }
    }

    public static function getFileContents(string $filePath): string
    {
        $result = file_get_contents($filePath);
        if (!is_string($result)) {
            throw new RuntimeException("Failed to get file contents; file path: `$filePath'");
        }
        return $result;
    }

    public static function putFileContents(string $filePath, string $contents): void
    {
        $contentsLen = strlen($contents);
        $numberOfBytesWritten = file_put_contents($filePath, $contents);
        if (!is_int($numberOfBytesWritten)) {
            throw new RuntimeException("Failed to put file contents; file path: `$filePath'; contents length: " . $contentsLen);
        }
        if ($numberOfBytesWritten !== $contentsLen) {
            throw new RuntimeException(
                "Number of bytes that were written does not match contents length; file path: `$filePath'; contents length: $contentsLen; number of bytes that were written: $numberOfBytesWritten",
            );
        }

        $fileSize = self::getFileSize($filePath);
        if ($fileSize !== $contentsLen) {
            throw new RuntimeException(
                "File size does not match contents length; file path: `$filePath'; contents length: $contentsLen; file size: $fileSize",
            );
        }
    }

    public static function getFileSize(string $filePath): int
    {
        $result = filesize($filePath);
        if (!is_int($result)) {
            throw new RuntimeException("Failed to get file size; file path: `$filePath'");
        }
        return $result;
    }

    /**
     * @phpstan-assert true $condition
     */
    public static function assert(bool $condition, string $message): void
    {
        if ($condition) {
            return;
        }

        throw new RuntimeException('Assertion failed: ' . $message);
    }

    /**
     * @param array<string, mixed> $dbgCtx
     *
     * @phpstan-assert array<array-key, mixed> $val
     *
     * @phpstan-return array<array-key, mixed>
     */
    public static function assertIsArray(mixed $val, array $dbgCtx): array
    {
        $dbgName = array_key_first($dbgCtx);
        self::assert(is_array($val), "is_array($dbgName) ; get_debug_type($dbgName): " . get_debug_type($val) . ' ; ' . json_encode($dbgCtx));
        return $val;
    }

    /**
     * @param array<string, mixed> $dbgCtx
     *
     * @phpstan-assert list<mixed> $val
     *
     * @phpstan-return list<mixed>
     */
    public static function assertIsList(mixed $val, array $dbgCtx): array
    {
        self::assertIsArray($val, $dbgCtx);
        $dbgName = array_key_first($dbgCtx);
        self::assert(array_is_list($val), "array_is_list($dbgName) ; " . json_encode($dbgCtx));
        return $val;
    }

    /**
     * @param Countable|array<mixed> $countable
     * @param array<string, mixed> $dbgCtx
     */
    public static function assertCount(int $expectedCount, Countable|array $countable, array $dbgCtx): void
    {
        $dbgName = array_key_first($dbgCtx);
        self::assert(count($countable) === $expectedCount, "count($dbgName) === $expectedCount ; " . json_encode($dbgCtx));
    }

    /**
     * @param array<string, mixed> $dbgCtx
     *
     * @phpstan-assert int $val
     *
     * @phpstan-return int
     *
     * @noinspection PhpUnused
     */
    public static function assertIsInt(mixed $val, array $dbgCtx): int
    {
        $dbgName = array_key_first($dbgCtx);
        self::assert(is_int($val), "is_int($dbgName) ; get_debug_type($dbgName): " . get_debug_type($val) . ' ; ' . json_encode($dbgCtx));
        return $val;
    }

    /**
     * @param array<string, mixed> $dbgCtx
     *
     * @phpstan-assert non-empty-string $val
     *
     * @phpstan-return non-empty-string
     */
    public static function assertNotEmptyString(string $val, array $dbgCtx): string
    {
        $dbgName = array_key_first($dbgCtx);
        self::assert($val !== '', "$dbgName !== '' ; " . json_encode($dbgCtx));
        return $val;
    }

    /**
     * @template TValue
     * *
     * @param TValue|false $val
     * @param array<string, mixed> $dbgCtx
     *
     * @phpstan-assert !false $val
     * @phpstan-assert TValue $val
     *
     * @phpstan-return TValue
     */
    public static function assertNotFalse(mixed $val, array $dbgCtx): mixed
    {
        $dbgName = array_key_first($dbgCtx);
        self::assert($val !== false, "$dbgName !== false ; " . json_encode($dbgCtx));
        return $val;
    }

    public static function assertFilesHaveSameContent(string $file1, string $file2): void
    {
        $file1Contents = self::getFileContents($file1);
        $file2Contents = self::getFileContents($file2);
        NoDevEnvUtil::assert($file1Contents === $file2Contents, '$file1Contents == $file1Content2 ; ' . json_encode(compact('file1', 'file2', 'file1Contents', 'file2Contents')));
    }

    public static function log(string $text): void
    {
        self::writeLineToStdErr($text);
    }

    public static function ensureStdErrIsDefined(): bool
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

    public static function writeLineToStdErr(string $text): void
    {
        if (self::ensureStdErrIsDefined()) {
            fwrite(STDERR, $text . PHP_EOL);
        }
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
                    self::log("Failed to clean up");
                    self::logThrowable($throwableFromCleanUp);
                }
            }
        }
    }

    public static function changeCurrentDirectoryRunCodeAndRestore(string $newCurrentDir, callable $code): void
    {
        $originalCurrentDir = self::getCurrentDirectory();
        self::changeCurrentDirectory($newCurrentDir);
        self::runCodeAndCleanUp($code, cleanUp: fn() => self::changeCurrentDirectory($originalCurrentDir));
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

    public static function createTempDirectoryGenerateUniqueName(string $dirNamePrefix): string
    {
        $fullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $dirNamePrefix . uniqid();
        self::createTempDirectory($fullPath);
        return $fullPath;
    }

    public static function createTempDirectory(string $newDirFullPath): void
    {
        self::log("Creating temporary directory $newDirFullPath");
        self::assertNotFalse(mkdir($newDirFullPath, recursive: true), compact('newDirFullPath'));
    }

    public static function createDirectory(string $newDirFullPath): void
    {
        self::log("Creating directory $newDirFullPath");
        self::assertNotFalse(mkdir($newDirFullPath, recursive: true), compact('newDirFullPath'));
    }

    public static function execShellCommand(string $shellCmd): void
    {
        self::log("Executing shell command: $shellCmd");
        $retVal = system($shellCmd, /* out */ $exitCode);
        self::assertNotFalse($retVal, compact('retVal'));
        self::assert($exitCode === 0, '$exitCode === 0' . ' ; shellCmd: ' . $shellCmd . ' ; exitCode: ' . $exitCode . ' ; retVal: ' . $retVal);
    }

    public static function copyDirectoryContents(string $fromDirPath, string $toDirPath): void
    {
        self::log("Copying directory contents from $fromDirPath to $toDirPath");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("xcopy /y /s /e \"$fromDirPath\\*\" \"$toDirPath\\\"");
        } else {
            self::execShellCommand("cp -r \"$fromDirPath/\"* \"$toDirPath/\"");
        }
    }

    /** @noinspection PhpUnused */
    public static function deleteFile(string $filePath): void
    {
        self::log("Deleting file $filePath");
        $retVal = unlink($filePath);
        self::assert($retVal, '$retVal' . ' ; $retVal: ' . json_encode($retVal) . ' ; filePath: ' . $filePath);
    }

    private static function shouldKeepTemporaryFiles(): bool
    {
        /** @var ?bool $cachedVal */
        static $cachedVal = null;

        if ($cachedVal === null) {
            $envVarVal = getenv(self::KEEP_TEMP_FILES_ENV_VAR_NAME);
            if (is_string($envVarVal) && (($parsedVal = BoolUtil::parseValue($envVarVal)) !== null)) {
                $cachedVal = $parsedVal;
            } else {
                $cachedVal = self::KEEP_TEMP_FILES_DEFAULT_VALUE;
            }
        }
        /** @var bool $cachedVal */

        return $cachedVal;
    }

    public static function deleteDirectoryContents(string $dirPath): void
    {
        self::log("Deleting directory contents $dirPath");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("del /Q /S \"$dirPath\"\\*");
        } else {
            self::execShellCommand("rm -rf \"$dirPath\"/*");
        }
    }

    public static function deleteDirectory(string $dirPath): void
    {
        self::log("Deleting directory $dirPath");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("del /Q /S \"$dirPath\"");
        } else {
            self::execShellCommand("rm -rf \"$dirPath\"");
        }
    }

    public static function deleteTempDirectory(string $dirPath): void
    {
        if (self::shouldKeepTemporaryFiles()) {
            self::log("Keeping temporary directory $dirPath");
        } else {
            self::deleteDirectory($dirPath);
        }
    }

    public static function getCurrentDirectory(): string
    {
        $currentDir = getcwd();
        self::assertNotFalse($currentDir, compact('currentDir'));
        return self::realPath($currentDir);
    }

    private static function changeCurrentDirectory(string $newCurrentDir): void
    {
        $chdirRetVal = chdir($newCurrentDir);
        self::assertNotFalse($chdirRetVal, compact('chdirRetVal'));
    }

    public static function listDirectoryContents(string $dirPath, int $recursiveDepth = 0): void
    {
        self::log("Contents  of directory $dirPath:");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("dir \"$dirPath\"");
        } else {
            self::execShellCommand("ls -al \"$dirPath\"");
        }

        if ($recursiveDepth === 0) {
            return;
        }

        foreach (new DirectoryIterator($dirPath) as $fileInfo) {
            if ($fileInfo->getFilename() === '.' || $fileInfo->getFilename() === '..') {
                continue;
            }

            self::listDirectoryContents($fileInfo->getRealPath(), $recursiveDepth - 1);
        }
    }

    public static function listFileContents(string $filePath): void
    {
        self::log("Contents of file $filePath:");
        if (self::isCurrentOsWindows()) {
            self::execShellCommand("type \"$filePath\"");
        } else {
            self::execShellCommand("cat \"$filePath\"");
        }
    }

    /**
     * @phpstan-return list{string, string}
     */
    public static function splitDependencyFullName(string $dependencyFullName): array
    {
        self::assertNotEmptyString($dependencyFullName, compact('dependencyFullName'));
        $packageFullNameLen = strlen($dependencyFullName);
        $result = [];
        $separatorPos = strpos($dependencyFullName, '/');
        if ($separatorPos === false) {
            return [$dependencyFullName, ''];
        }
        $result[] = substr($dependencyFullName, 0, $separatorPos);
        $result[] = ($separatorPos === ($packageFullNameLen - 1)) ? '' : substr($dependencyFullName, $separatorPos + 1);
        return $result;
    }

    public static function getRepoRootPath(): string
    {
        // __DIR__ is "<repo root>/tools/build"
        return NoDevEnvUtil::realPath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    }

    /**
     * @param array<string> $parts
     */
    private static function buildShellCommand(array $parts): string
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
     * @param array<string, string> $envVars
     */
    private static function convertEnvVarsToCmdLinePart(array $envVars): string
    {
        $cmdParts = [];
        foreach ($envVars as $envVarName => $envVarVal) {
            $cmdParts[] = self::isCurrentOsWindows() ? "set \"$envVarName=$envVarVal\" &&" : "$envVarName=\"$envVarVal\"";
        }
        return self::buildShellCommand($cmdParts);
    }

    public static function execComposerInstallShellCommand(bool $withDev, string $additionalArgs = '', array $envVars = []): void
    {
        $cmdParts = [];
        $cmdParts[] = self::convertEnvVarsToCmdLinePart($envVars);
        $cmdParts[] = 'composer ' . self::COMPOSER_INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS . ' --no-interaction';
        $cmdParts[] = $withDev ? '' : '--no-dev';
        $cmdParts[] = $additionalArgs;
        $cmdParts[] = 'install';
        NoDevEnvUtil::execShellCommand(self::buildShellCommand($cmdParts));
    }

    public static function buildToGeneratedFileFullPath(string $repoRootPath, string $fileName): string
    {
        return NoDevEnvUtil::realPath($repoRootPath . DIRECTORY_SEPARATOR . self::GENERATED_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $fileName);
    }

    public static function buildGeneratedComposerJsonFileNameForPhpVersion(string $envKind, string $phpVersionNoDot): string
    {
        /**
         * @see build_generated_composer_json_file_name() finction in tool/shared.sh
         */

        return $envKind . ($phpVersionNoDot === '81' ? ('_adapted_to_' . $phpVersionNoDot) : '') . '.json';
    }

    public static function buildGeneratedComposerJsonFileNameForCurrentPhpVersion(string $envKind): string
    {
        /**
         * @see build_generated_composer_json_file_name() finction in tool/shared.sh
         */

        return self::buildGeneratedComposerJsonFileNameForPhpVersion($envKind, '' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION);
    }

    public static function buildGeneratedComposerLockFileNameForCurrentPhpVersion(string $envKind): string
    {
        /**
         * @see build_generated_composer_lock_file_name() finction in tool/shared.sh
         */
        return $envKind . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.lock';
    }

    public static function verifyThatComposerJsonAndLockAreInSync(?string $composerJsonFilePath = null): void
    {
        $cmdParts = [];
        if ($composerJsonFilePath !== null) {
            $cmdParts[] = self::convertEnvVarsToCmdLinePart([NoDevEnvUtil::COMPOSER_ENV_VAR_NAME => $composerJsonFilePath]);
        }
        $cmdParts[] = 'composer --check-lock --no-check-all validate';
        NoDevEnvUtil::execShellCommand(self::buildShellCommand($cmdParts));
    }
}
