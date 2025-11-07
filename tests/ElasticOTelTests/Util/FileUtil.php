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

use Closure;
use DirectoryIterator;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class FileUtil
{
    use StaticClassTrait;

    public static function normalizePath(string $inAbsolutePath): string
    {
        $result = realpath($inAbsolutePath);
        if ($result === false) {
            throw new TestsInfraException(ExceptionUtil::buildMessage("realpath failed", compact('inAbsolutePath')));
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
        foreach (TextUtilForTests::iterateOverChars($path) as $pathCharAsInt) {
            $result .= $pathCharAsInt === $unixDirectorySeparatorAsInt ? DIRECTORY_SEPARATOR : chr($pathCharAsInt);
        }
        return $result;
    }

    /**
     * @param Closure(string): void $consumeLine
     */
    public static function readLines(string $filePath, Closure $consumeLine): void
    {
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle === false) {
            throw new TestsInfraException(ExceptionUtil::buildMessage('Failed to open file', compact('filePath')));
        }

        while (($line = fgets($fileHandle)) !== false) {
            $consumeLine($line);
        }

        if (!feof($fileHandle)) {
            throw new TestsInfraException(ExceptionUtil::buildMessage('Failed to read from file', compact('filePath')));
        }

        fclose($fileHandle);
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

    public static function createTempFile(?string $dbgTempFilePurpose = null): string
    {
        $tempFileFullPath = tempnam(sys_get_temp_dir(), prefix: 'ElasticOTelTests_');
        $logCategory = LogCategoryForTests::TEST_INFRA;
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass($logCategory, __NAMESPACE__, __CLASS__, __FILE__);

        if ($tempFileFullPath === false) {
            ($loggerProxy = $logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->includeStackTrace()->log('Failed to create a temporary file', compact('dbgTempFilePurpose'));
            Assert::fail(LoggableToString::convert(compact('dbgTempFilePurpose')));
        }

        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Created a temporary file', compact('tempFileFullPath', 'dbgTempFilePurpose'));

        return $tempFileFullPath;
    }


    /**
     * @return iterable<SplFileInfo>
     */
    public static function iterateDirectory(string $dirPath): iterable
    {
        foreach (new DirectoryIterator($dirPath) as $fileInfo) {
            if ($fileInfo->getFilename() === '.' || $fileInfo->getFilename() === '..') {
                continue;
            }

            yield $fileInfo;
        }
    }

    /**
     * @param string $dirFullPath
     *
     * @return iterable<SplFileInfo>
     */
    public static function iterateOverFilesInDirectoryRecursively(string $dirFullPath): iterable
    {
        $dirIter = new RecursiveDirectoryIterator($dirFullPath);
        foreach (new RecursiveIteratorIterator($dirIter) as $fileInfo) {
            Assert::assertInstanceOf(SplFileInfo::class, $fileInfo);
            if ($fileInfo->isFile()) {
                yield $fileInfo;
            }
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

    /** @noinspection PhpUnused */
    public static function putFileContents(string $filePath, string $contents): int
    {
        $result = file_put_contents($filePath, $contents);
        if (!is_int($result)) {
            throw new RuntimeException("Failed to put file contents; file path: `$filePath'; contents length: " . strlen($contents));
        }
        return $result;
    }
}
