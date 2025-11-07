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

use Composer\Script\Event;
use Countable;
use DirectoryIterator;
use Elastic\OTel\AutoloaderElasticOTelClasses;
use Elastic\OTel\PhpPartFacade;
use Elastic\OTel\Util\BoolUtil;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * This class is used in scripts section of composer.json
 *
 * @noinspection PhpUnused
 *
 * @phpstan-type EnvVars array<string, string>
 */
final class ComposerScripts
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

    /**
     * @see elastic_otel_php_build_tools_composer_lock_files_dir in tool/shared.sh
     */
    private const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    public const DEV_ENV_KIND = 'dev';
    public const PROD_ENV_KIND = 'prod';
    public const TESTS_ENV_KIND = 'tests';

    public const COMPOSER_ENV_VAR_NAME = 'COMPOSER';

    public const PATH_ENV_VAR_NAME = 'PATH';

    private const FAILURE_EXIT_CODE = 1;

    private static ?bool $isStderrDefined = null;

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function adaptComposerJsonDownloadAndAdaptPackages(Event $event): void
    {
        self::runCmdLineImpl(fn() => AdaptPackagesToPhp81::adaptComposerJsonDownloadAndAdaptPackages(self::getCommandLineArgs($event)));
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function installDevSelectLock(): void
    {
        self::runCmdLineImpl(
            function (): void {
                self::verifyGeneratedComposerLockFiles();

                self::selectGeneratedDevLock();

                if (AdaptPackagesToPhp81::isCurrentPhpVersion81()) {
                    AdaptPackagesToPhp81::installSelectJsonLock(self::DEV_ENV_KIND);
                } else {
                    self::verifyThatComposerJsonAndLockAreInSync();
                    self::execComposerInstallShellCommand(withDev: true);
                }
            }
        );
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function installProdSelectJsonLock(): void
    {
        self::runCmdLineImpl(
            function (): void {
                self::verifyGeneratedComposerLockFiles();

                if (AdaptPackagesToPhp81::isCurrentPhpVersion81()) {
                    AdaptPackagesToPhp81::installSelectJsonLock(self::PROD_ENV_KIND);
                } else {
                    self::installSelectJsonLock(self::PROD_ENV_KIND);
                }
            }
        );
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function runConfigurePhpTemplatesScript(): void
    {
        self::runCmdLineImpl(
            function (): void {
                if (self::isCurrentOsWindows()) {
                    self::log('<configure PHP templates> is not implemented (yet?) for Windows');
                } else {
                    self::execShellCommand('./tools/build/configure_php_templates.sh');
                }
            }
        );
    }

    /**
     * @param callable(): void $code
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function runCmdLineImpl(callable $code): void
    {
        $exitCode = 0;

        try {
            require self::getRepoRootPath() . DIRECTORY_SEPARATOR
                . 'prod' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'ElasticOTel' . DIRECTORY_SEPARATOR . 'AutoloaderElasticOTelClasses.php';
            AutoloaderElasticOTelClasses::register('Elastic\\OTel', __DIR__);
            self::ensureNoRefsToThisRepoVendorFromEnvVarPath();
            $code();
        } catch (Throwable $throwable) {
            $exitCode = self::FAILURE_EXIT_CODE;
            self::log('Caught throwable');
            self::logThrowable($throwable);
        }

        exit($exitCode);
    }

    private static function shouldAllowDirectCommand(): bool
    {
        $envVarVal = getenv(self::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME);

        if (!is_string($envVarVal)) {
            return false;
        }

        return match (strtolower($envVarVal)) {
            'true', '1' => true,
            default => false,
        };
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function regularInstallOrUpdateNotSupported(): void
    {
        if (self::shouldAllowDirectCommand()) {
            return;
        }

        self::log("Do not run `composer install' or `composer update' directly.");

        self::log(
            "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer install'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . 'composer run-script -- install_dev_select_generated_lock'
            . "\n"
            . "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer update'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . './tools/build/generate_composer_lock_files.sh && composer run-script -- install_dev_select_generated_lock',
        );

        exit(self::FAILURE_EXIT_CODE);
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    private static function selectGeneratedDevLock(): void
    {
        self::log('Selecting dev_<PHP version>.lock ...');

        self::copyComposeLockCurrentPhpVersion(self::DEV_ENV_KIND);

        self::log('Selected dev_<PHP version>.lock');
    }

    /** @noinspection PhpSameParameterValueInspection */
    private static function copyComposeLockCurrentPhpVersion(string $envKind): void
    {
        self::log('Copying composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');

        $repoRootPath = self::getRepoRootPath();
        $srcFilePath = self::buildToGeneratedFileFullPath($repoRootPath, self::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
        if (!file_exists($srcFilePath)) {
            throw new RuntimeException("File $srcFilePath does not exist");
        }

        $dstFilePath = $repoRootPath . DIRECTORY_SEPARATOR . 'composer.lock';

        self::copyFile($srcFilePath, $dstFilePath);

        self::log('Copied composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');
    }

    /**
     * @return list<string>
     */
    private static function getCommandLineArgs(Event $event): array
    {
        $eventArguments = $event->getArguments();
        self::assertIsList($eventArguments, compact('eventArguments'));
        return $eventArguments;
    }

    public static function verifyGeneratedComposerLockFiles(): void
    {
        $repoRootPath = self::getRepoRootPath();
        $repoRootJsonPath = $repoRootPath . DIRECTORY_SEPARATOR . self::COMPOSER_JSON_FILE_NAME;
        $generatedDevJsonPath = self::buildToGeneratedFileFullPath($repoRootPath, self::buildGeneratedComposerJsonFileNameForPhpVersion(self::DEV_ENV_KIND, 'not 81'));
        self::assertFilesHaveSameContent($repoRootJsonPath, $generatedDevJsonPath);
    }

    public static function installSelectJsonLock(string $envKind): void
    {
        $repoRootPath = self::getRepoRootPath();
        self::runCodeOnTempDir(
            self::fqClassNameToShort(__CLASS__),
            function (string $tempDir) use ($envKind, $repoRootPath): void {
                $namePrefix = 'composer_to_use';

                $generatedComposerJson = self::buildToGeneratedFileFullPath($repoRootPath, self::buildGeneratedComposerJsonFileNameForCurrentPhpVersion($envKind));
                $composerJsonToUse = self::partsToPath($tempDir, $namePrefix . '.json');
                self::copyFile($generatedComposerJson, $composerJsonToUse);

                $generatedComposerLock = self::buildToGeneratedFileFullPath($repoRootPath, self::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
                $composerLockToUse = self::partsToPath($tempDir, $namePrefix . '.lock');
                self::copyFile($generatedComposerLock, $composerLockToUse);

                self::verifyThatComposerJsonAndLockAreInSync(composerJsonFilePath: $composerJsonToUse);

                self::execComposerInstallShellCommand(
                    withDev: self::convertEnvKindToWithDev($envKind),
                    envVars: [
                        self::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true),
                        self::COMPOSER_ENV_VAR_NAME => $composerJsonToUse,
                    ],
                );
            },
        );
    }

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
     * @param ?array<string, mixed> $dbgCtx
     */
    private static function convertAssertDbgCtxToStringToAppend(?array $dbgCtx = null): string
    {
        return $dbgCtx === null ? '' : (' ; ' . json_encode($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert array<array-key, mixed> $val
     *
     * @phpstan-return array<array-key, mixed>
     */
    public static function assertIsArray(mixed $val, ?array $dbgCtx = null): array
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(is_array($val), "is_array($dbgName) ; get_debug_type($dbgName): " . get_debug_type($val) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert list<mixed> $val
     *
     * @phpstan-return list<mixed>
     */
    public static function assertIsList(mixed $val, ?array $dbgCtx = null): array
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assertIsArray($val, $dbgCtx);
        self::assert(array_is_list($val), "array_is_list($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @param Countable|array<mixed> $countable
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertCount(int $expectedCount, Countable|array $countable, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(count($countable) === $expectedCount, "count($dbgName) === $expectedCount" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @template TKey of array-key
     *
     * @param TKey $key
     * @param array<TKey, mixed> $array
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert array{key: mixed, ...} $array
     */
    public static function assertArrayHasKey(mixed $key, array $array, ?array $dbgCtx = null): void
    {
        self::assert(array_key_exists($key, $array), 'array_key_exists($key, $array)' . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param array-key $key
     * @param array<array-key, mixed> $array
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertArrayNotHasKey(mixed $key, array $array, ?array $dbgCtx = null): void
    {
        self::assert(!array_key_exists($key, $array), '!array_key_exists($key, $array)' . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert int $val
     *
     * @phpstan-return int
     *
     * @noinspection PhpUnused
     */
    public static function assertIsInt(mixed $val, ?array $dbgCtx = null): int
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(is_int($val), "is_int($dbgName) ; get_debug_type($dbgName): " . get_debug_type($val) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert string $val
     *
     * @phpstan-return string
     */
    public static function assertIsString(mixed $val, ?array $dbgCtx = null): string
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(is_string($val), "is_string($dbgName) ; get_debug_type($dbgName): " . get_debug_type($val) . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert non-empty-string $val
     *
     * @phpstan-return non-empty-string
     */
    public static function assertNotEmptyString(string $val, ?array $dbgCtx = null): string
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert($val !== '', "$dbgName !== ''" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @template TValue
     * *
     * @param TValue|false $val
     * @param ?array<string, mixed> $dbgCtx
     *
     * @phpstan-assert !false $val
     * @phpstan-assert TValue $val
     *
     * @phpstan-return TValue
     */
    public static function assertNotFalse(mixed $val, ?array $dbgCtx = null): mixed
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert($val !== false, "$dbgName !== false" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
        return $val;
    }

    /**
     * @param string $filePath
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertFileExists(string $filePath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(file_exists($filePath), "file_exists($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param string $filePath
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertFileDoesNotExist(string $filePath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(!file_exists($filePath), "!file_exists($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    /**
     * @param string $dirPath
     * @param ?array<string, mixed> $dbgCtx
     */
    public static function assertDirectoryExists(string $dirPath, ?array $dbgCtx = null): void
    {
        $dbgName = $dbgCtx === null ? '$val' : array_key_first($dbgCtx);
        self::assert(is_dir($dirPath), "file_exists($dbgName) && is_dir($dbgName)" . self::convertAssertDbgCtxToStringToAppend($dbgCtx));
    }

    public static function assertFilesHaveSameContent(string $file1, string $file2): void
    {
        $file1Contents = self::getFileContents($file1);
        $file2Contents = self::getFileContents($file2);
        self::assert($file1Contents === $file2Contents, '$file1Contents == $file1Content2 ; ' . json_encode(compact('file1', 'file2', 'file1Contents', 'file2Contents')));
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
        return self::realPath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
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
     * @param EnvVars $envVars
     */
    private static function convertEnvVarsToCmdLinePart(array $envVars): string
    {
        $cmdParts = [];
        foreach ($envVars as $envVarName => $envVarVal) {
            $cmdParts[] = self::isCurrentOsWindows() ? "set \"$envVarName=$envVarVal\" &&" : "$envVarName=\"$envVarVal\"";
        }
        return self::buildShellCommand($cmdParts);
    }

    /**
     * @param EnvVars $envVars
     */
    public static function execComposerInstallShellCommand(bool $withDev, string $additionalArgs = '', array $envVars = []): void
    {
        $cmdParts = [];
        $cmdParts[] = self::convertEnvVarsToCmdLinePart($envVars);
        $cmdParts[] = 'composer ' . self::COMPOSER_INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS . ' --no-interaction';
        $cmdParts[] = $withDev ? '' : '--no-dev';
        $cmdParts[] = $additionalArgs;
        $cmdParts[] = 'install';
        self::execShellCommand(self::buildShellCommand($cmdParts));
    }

    public static function buildToGeneratedFileFullPath(string $repoRootPath, string $fileName): string
    {
        return self::realPath($repoRootPath . DIRECTORY_SEPARATOR . self::GENERATED_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $fileName);
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
            $cmdParts[] = self::convertEnvVarsToCmdLinePart([self::COMPOSER_ENV_VAR_NAME => $composerJsonFilePath]);
        }
        $cmdParts[] = 'composer --check-lock --no-check-all validate';
        self::execShellCommand(self::buildShellCommand($cmdParts));
    }

    public static function ensureNoRefsToThisRepoVendorFromEnvVarPath(): void
    {
        $pathEnvVarVal = getenv(self::PATH_ENV_VAR_NAME);
        if (!is_string($pathEnvVarVal)) {
            return;
        }

        self::log("Ensuring PATH env var does not reference this repo vendor directory; PATH env var: $pathEnvVarVal");

        /** @var ?non-empty-string $pathEnvVarSep */
        static $pathEnvVarSep = null;
        if ($pathEnvVarSep === null) {
            $pathEnvVarSep = self::isCurrentOsWindows() ? ';' : ':';
        }
        /** @var non-empty-string $pathEnvVarSep */

        $pathEnvVarParts = explode($pathEnvVarSep, $pathEnvVarVal);
        $thisRepoVendorPathPrefix = self::getRepoRootPath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
        $pathEnvVarPartsToKeep = [];
        $wereAnyPartsDropped = false;
        foreach ($pathEnvVarParts as $pathEnvVarPart) {
            if (str_starts_with($pathEnvVarPart, $thisRepoVendorPathPrefix)) {
                $wereAnyPartsDropped = true;
            } else {
                $pathEnvVarPartsToKeep[] = $pathEnvVarPart;
            }
        }

        if (!$wereAnyPartsDropped) {
            self::log("No to need to change PATH env var from $pathEnvVarVal");
            return;
        }

        $pathEnvVarValToKeep = implode($pathEnvVarSep, $pathEnvVarPartsToKeep);
        PhpPartFacade::setEnvVar(self::PATH_ENV_VAR_NAME, $pathEnvVarValToKeep);
        self::log("Changed PATH env var from $pathEnvVarVal to $pathEnvVarValToKeep");
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
    public static function runCodeOnTempDir(string $tempDirNamePrefix, callable $code): mixed
    {
        $tempDir = self::createTempDirectoryGenerateUniqueName($tempDirNamePrefix);
        return self::runCodeAndCleanUp(
            function () use ($tempDir, $code): mixed {
                return $code($tempDir);
            },
            cleanUp: fn() => self::deleteTempDirectory($tempDir)
        );
    }

    public static function convertEnvKindToWithDev(string $envKind): bool
    {
        return match ($envKind) {
            self::DEV_ENV_KIND, self::TESTS_ENV_KIND => true,
            self::PROD_ENV_KIND => false,
            default => throw new RuntimeException("Unexpected envKind: $envKind"),
        };
    }

    public static function partsToPath(string ...$parts): string
    {
        $result = '';
        foreach ($parts as $part) {
            if ($result !== '' && $part !== '') {
                $result .= DIRECTORY_SEPARATOR;
            }
            $result .= $part;
        }
        return $result;
    }

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
     *
     * @noinspection PhpSameParameterValueInspection
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
    private static function iterateOverChars(string $input): iterable
    {
        foreach (self::generateRangeUpTo(strlen($input)) as $i) {
            yield ord($input[$i]);
        }
    }

    public static function adaptUnixDirectorySeparators(string $path): string
    {
        /** @phpstan-var string $unixDirectorySeparator */
        static $unixDirectorySeparator = '/';

        if (DIRECTORY_SEPARATOR === $unixDirectorySeparator) {
            return $path;
        }

        static $unixDirectorySeparatorAsInt = null;
        if ($unixDirectorySeparatorAsInt === null) {
            $unixDirectorySeparatorAsInt = ord($unixDirectorySeparator);
        }

        $result = '';
        foreach (self::iterateOverChars($path) as $pathCharAsInt) {
            $result .= $pathCharAsInt === $unixDirectorySeparatorAsInt ? DIRECTORY_SEPARATOR : chr($pathCharAsInt);
        }
        return $result;
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
}
