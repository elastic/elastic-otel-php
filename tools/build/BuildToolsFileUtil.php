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
use Elastic\OTel\PhpPartFacade;
use RuntimeException;

/**
 * @phpstan-import-type EnvVars from BuildToolsUtil
 */
final class BuildToolsFileUtil
{
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    private const KEEP_TEMP_FILES_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES';

    public static function realPath(string $path): string
    {
        $retVal = realpath($path);
        self::assertNotFalse($retVal, compact('retVal', 'path'));
        return $retVal;
    }

    // TODO: Sergey Kleyman: REMOVE: PhpUnused
    /** @noinspection PhpUnused */
    public static function copyFile(string $fromFilePath, string $toFilePath, bool $allowOverwrite = false): void
    {
        self::logInfo(__LINE__, __METHOD__, "Copying file $fromFilePath to $toFilePath");
        $allowOverwriteOpt = ($allowOverwrite ? (BuildToolsUtil::isCurrentOsWindows() ? '/y' : '-f') : '');
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "copy $allowOverwriteOpt \"$fromFilePath\" \"$toFilePath\""
                : "cp $allowOverwriteOpt \"$fromFilePath\" \"$toFilePath\""
        );
    }

    public static function getFileContents(string $filePath): string
    {
        $result = file_get_contents($filePath);
        if (!is_string($result)) {
            throw new RuntimeException("Failed to get file contents; file path: `$filePath'");
        }
        return $result;
    }

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
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
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
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

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
    public static function copyDirectoryContents(string $fromDirPath, string $toDirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Copying directory contents from $fromDirPath to $toDirPath");
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "xcopy /y /s /e \"$fromDirPath\\*\" \"$toDirPath\\\""
                : "cp -r \"$fromDirPath/\"* \"$toDirPath/\"",
        );
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

    // TODO: Sergey Kleyman: REMOVE: PhpUnused
    /** @noinspection PhpUnused */
    public static function deleteDirectoryContents(string $dirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Deleting directory contents $dirPath");
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "DEL /F /Q /S \"$dirPath\"\\*"
                : "rm -rf \"$dirPath\"/*",
        );
    }

    public static function deleteDirectory(string $dirPath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Deleting directory $dirPath");
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "DEL /F /Q /S \"$dirPath\" && RD /S /Q \"$dirPath\""
                : "rm -rf \"$dirPath\""
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
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
    public static function listDirectoryContents(string $dirPath, int $recursiveDepth = 0): void
    {
        self::logInfo(__LINE__, __METHOD__, "Contents  of directory $dirPath:");
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "dir \"$dirPath\""
                : "ls -al \"$dirPath\""
        );

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

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
    public static function listFileContents(string $filePath): void
    {
        self::logInfo(__LINE__, __METHOD__, "Contents of file $filePath:");
        BuildToolsUtil::execShellCommand(
            BuildToolsUtil::isCurrentOsWindows()
                ? "type \"$filePath\""
                : "cat \"$filePath\""
        );
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

    /**
     * TODO: Sergey Kleyman: REMOVE: PhpUnused
     * @noinspection PhpUnused
     */
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
        foreach (BuildToolsUtil::iterateOverChars($path) as $pathCharAsInt) {
            $result .= $pathCharAsInt === $unixDirectorySeparatorAsInt ? DIRECTORY_SEPARATOR : chr($pathCharAsInt);
        }
        return $result;
    }

    /**
     * Must be defined in class using BuildToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
