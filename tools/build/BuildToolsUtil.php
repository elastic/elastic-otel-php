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

use DirectoryIterator;
use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\PhpPartFacade;
use JsonException;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * @phpstan-type EnvVars array<string, string>
 */
final class BuildToolsUtil
{
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    private const KEEP_TEMP_FILES_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES';

    public const FAILURE_EXIT_CODE = 1;

    /**
     * @param callable(): void $code
     */
    public static function runCmdLineImpl(string $calledFromFqMethod, callable $code): void
    {
        self::logInfo(__LINE__, __METHOD__, 'Running code for command line: ' . BuildToolsLog::shortenFqMethod($calledFromFqMethod), ['log level' => BuildToolsLog::getMaxEnabledLevel()->name]);

        $exitCode = 0;

        try {
            $code();
        } catch (Throwable $throwable) {
            $exitCode = self::FAILURE_EXIT_CODE;
            self::logThrowable(LogLevel::critical, __LINE__, __METHOD__, $throwable);
        }

        self::logInfo(__LINE__, __METHOD__, 'Finished running code for command line: ' . BuildToolsLog::shortenFqMethod($calledFromFqMethod), compact('exitCode'));
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

    public static function execShellCommand(string $cmd): void
    {
        self::logInfo(__LINE__, __METHOD__, "Executing shell command: [$cmd]");
        $systemRetVal = system($cmd, /* out */ $exitCode);
        self::assertNotFalse($systemRetVal, compact('systemRetVal'));
        self::assert($exitCode === 0, '$exitCode === 0 ; ' . json_encode(compact('exitCode', 'cmd', 'systemRetVal')));
    }

    public static function quoteForShellCommand(string ...$parts): string
    {
        return array_reduce($parts, fn($carry, $part) => $carry . ($carry === '' ? '' : ' ') . " \"$part\"", '');
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
        $tempDir = self::createTempDirectoryGenerateUniqueName($tempDirNamePrefix);
        return self::runCodeAndCleanUp(
            function () use ($tempDir, $code): mixed {
                return $code($tempDir);
            },
            cleanUp: fn() => self::deleteTempDirectory($tempDir)
        );
    }

    public static function changeCurrentDirectoryRunCodeAndRestore(string $newCurrentDir, callable $code): void
    {
        $originalCurrentDir = self::getCurrentDirectory();
        self::changeCurrentDirectory($newCurrentDir);
        self::runCodeAndCleanUp($code, cleanUp: fn() => self::changeCurrentDirectory($originalCurrentDir));
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

    public static function realPath(string $path): string
    {
        $retVal = realpath($path);
        self::assertNotFalse($retVal, compact('retVal', 'path'));
        return $retVal;
    }

    public static function copyFile(string $fromFilePath, string $toFilePath, bool $allowOverwrite = false): void
    {
        self::logInfo(__LINE__, __METHOD__, "Copying file $fromFilePath to $toFilePath");
        $cmdAndOpts = self::isCurrentOsWindows() ? 'copy' : 'cp';
        if ($allowOverwrite) {
            $cmdAndOpts .= ' ' . (self::isCurrentOsWindows() ? '/y' : '-f');
        }
        self::execShellCommand($cmdAndOpts . ' ' . self::quoteForShellCommand($fromFilePath, $toFilePath));
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

    public static function createTempDirectoryGenerateUniqueName(string $dirNamePrefix): string
    {
        $fullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $dirNamePrefix . uniqid();
        self::createTempDirectory($fullPath);
        return $fullPath;
    }

    public static function createTempDirectory(string $newDirFullPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Creating temporary directory $newDirFullPath");
        if (is_dir($newDirFullPath)) {
            self::logInfo(__LINE__, __METHOD__, "Directory $newDirFullPath already exists");
            return;
        }
        self::assertNotFalse(mkdir($newDirFullPath, recursive: true), compact('newDirFullPath'));
    }

    public static function createDirectory(string $newDirFullPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Creating directory $newDirFullPath");
        self::assertNotFalse(mkdir($newDirFullPath, recursive: true), compact('newDirFullPath'));
    }

    public static function copyDirectoryContents(string $fromDirPath, string $toDirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Copying directory contents from $fromDirPath to $toDirPath");

        if (self::isCurrentOsWindows()) {
            // Windows xcopy copies the contents of the source directory to the destination. It does not copy the top source directory itself.
            self::execShellCommand('xcopy /y /s /e ' . self::quoteForShellCommand($fromDirPath, $toDirPath));
            return;
        }

        // cp -r "$fromDirPath/"* "$toDirPath" is problematic because with many items in the source directory
        // and wildcard expansion the command line might become too long
        foreach (self::iterateOverDirectoryContents($fromDirPath) as $fileInfo) {
            // cp does copy the top source directory itself so destination should the root 'to' directory
            self::execShellCommand('cp -r ' . self::quoteForShellCommand($fileInfo->getRealPath(), $toDirPath));
        }
    }

    /** @noinspection PhpUnused */
    public static function moveDirectoryContents(string $fromDirPath, string $toDirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Moving directory contents from $fromDirPath to $toDirPath");

        foreach (self::iterateOverDirectoryContents($fromDirPath) as $fileInfo) {
            rename($fileInfo->getRealPath(), self::partsToPath($toDirPath, $fileInfo->getBasename()));
        }
    }

    /** @noinspection PhpUnused */
    public static function deleteFile(string $filePath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Deleting file $filePath");
        $retVal = unlink($filePath);
        self::assert($retVal, '$retVal' . ' ; $retVal: ' . json_encode($retVal) . ' ; filePath: ' . $filePath);
    }

    private static function shouldKeepTemporaryFiles(): bool
    {
        /** @var ?bool $cachedVal */
        static $cachedVal = null;

        if ($cachedVal === null) {
            $cachedVal = PhpPartFacade::getBoolEnvVar(self::KEEP_TEMP_FILES_ENV_VAR_NAME, default: false);
        }
        /** @var bool $cachedVal */

        return $cachedVal;
    }

    public static function deleteDirectory(string $dirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Deleting directory $dirPath");

        self::execShellCommand(
            self::isCurrentOsWindows()
                ? ('del /F /Q /S ' . self::quoteForShellCommand($dirPath) . ' && rd /S /Q ' . self::quoteForShellCommand($dirPath))
                : ('rm -rf ' .  self::quoteForShellCommand($dirPath)),
        );
    }

    public static function deleteTempDirectory(string $dirPath): void
    {
        if (self::shouldKeepTemporaryFiles()) {
            self::logInfo(__LINE__, __METHOD__, "Keeping temporary directory $dirPath");
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

    public static function changeCurrentDirectory(string $newCurrentDir): void
    {
        $chdirRetVal = chdir($newCurrentDir);
        self::assertNotFalse($chdirRetVal, compact('chdirRetVal'));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    public static function iterateOverDirectoryContents(string $dirPath): iterable
    {
        foreach (new DirectoryIterator($dirPath) as $fileInfo) {
            if ($fileInfo->getFilename() === '.' || $fileInfo->getFilename() === '..') {
                continue;
            }

            yield $fileInfo;
        }
    }

    public static function listDirectoryContents(string $dirPath, int $recursiveDepth = 0): void
    {
        self::logInfo(__LINE__, __METHOD__, "Contents  of directory $dirPath:");

        self::execShellCommand((self::isCurrentOsWindows() ? 'dir' :  'ls -al') . ' ' . self::quoteForShellCommand($dirPath));

        if ($recursiveDepth === 0) {
            return;
        }

        foreach (self::iterateOverDirectoryContents($dirPath) as $fileInfo) {
            self::listDirectoryContents($fileInfo->getRealPath(), $recursiveDepth - 1);
        }
    }

    public static function listFileContents(string $filePath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Contents of file $filePath:");
        self::execShellCommand((self::isCurrentOsWindows() ? 'type' : 'cat') . ' ' . self::quoteForShellCommand($filePath));
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
