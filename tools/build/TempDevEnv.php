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
use ElasticOTelTests\BootstrapTests;
use ElasticOTelTests\Util\JsonUtil;

/**
 * @phpstan-type EntriesToExcludeFromRepoTempCopyKey 'name_prefixes'|'names'|'directory_names'
 * @phpstan-type EntriesToExcludeFromRepoTempCopy array<EntriesToExcludeFromRepoTempCopyKey, list<string>>
 */
final class TempDevEnv
{
    /**
     * @param callable(string $repoTempCopyRootDir): void $code
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function runCodeDependingOnDevEnv(callable $code): void
    {
        $repoTempCopyRootDir = NoDevEnvUtil::createTempDirectoryGenerateUniqueName('ComposerScripts_temp_dev_env_');
        NoDevEnvUtil::runCodeAndCleanUp(
            function () use ($repoTempCopyRootDir, $code) {
                self::bootstrap($repoTempCopyRootDir);
                $code($repoTempCopyRootDir);
            },
            cleanUp: fn() => NoDevEnvUtil::deleteTempDirectory($repoTempCopyRootDir)
        );
    }

    private static function bootstrap(string $repoTempCopyRootDir): void
    {
        NoDevEnvUtil::log("Bootstrapping temporary dev environment at $repoTempCopyRootDir");

        self::copyRepoContents(NoDevEnvUtil::getRepoRootPath(), $repoTempCopyRootDir);
        self::minimizeTempDevEnvComposerJson($repoTempCopyRootDir . DIRECTORY_SEPARATOR . NoDevEnvUtil::COMPOSER_JSON_FILE_NAME);
        self::installVendorOnRepoTempCopy($repoTempCopyRootDir);
        require NoDevEnvUtil::realPath($repoTempCopyRootDir . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'bootstrap.php');
        BootstrapTests::bootstrapTool('ComposerScripts');
    }

    private static function copyRepoContents(string $repoRootDir, string $repoTempCopyRootDir): void
    {
        NoDevEnvUtil::log("Copying repo contents from $repoRootDir to $repoTempCopyRootDir");

        $entriesToExclude = self::buildEntriesToExcludeFromRepoTempCopy($repoRootDir);
        NoDevEnvUtil::log(json_encode(compact('entriesToExclude'))); // @phpstan-ignore argument.type

        /** @var DirectoryIterator $fileInfo */
        foreach (new DirectoryIterator($repoRootDir) as $fileInfo) {
            if (
                $fileInfo->getFilename() === '.' || $fileInfo->getFilename() === '..'
                || self::shouldEntryBeExcludedFromRepoTempCopy($fileInfo->getBasename(), $fileInfo->isDir(), $entriesToExclude)
            ) {
                continue;
            }

            $dstFullPath = $repoTempCopyRootDir . DIRECTORY_SEPARATOR . $fileInfo->getBasename();
            if ($fileInfo->isDir()) {
                NoDevEnvUtil::createTempDirectory($dstFullPath);
                NoDevEnvUtil::copyDirectoryContents($fileInfo->getRealPath(), $dstFullPath);
            } else {
                NoDevEnvUtil::copyFile($fileInfo->getRealPath(), $dstFullPath);
            }
        }
    }

    /**
     * @return EntriesToExcludeFromRepoTempCopy
     */
    private static function buildEntriesToExcludeFromRepoTempCopy(string $repoRootDir): array
    {
        $result = ['name_prefixes' => [], 'names' => [], 'directory_names' => []];
        self::readGitIgnore($repoRootDir, /* ref */ $result);
        $result['names'][] = '.editorconfig';
        $result['directory_names'][] = '.git';
        $result['directory_names'][] = '.github';
        $result['names'][] = '.gitignore';
        $result['directory_names'][] = 'docker';
        $result['directory_names'][] = 'docs';
        $result['directory_names'][] = 'generated_composer_lock_files';
        $result['directory_names'][] = 'packaging';
        return $result;
    }

    /**
     * @param EntriesToExcludeFromRepoTempCopy $result
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function readGitIgnore(string $repoRootDir, /* ref */ array &$result): void
    {
        $filePath = $repoRootDir . DIRECTORY_SEPARATOR . '.gitignore';
        NoDevEnvUtil::log("Reading $filePath");

        $fileHandle = fopen($filePath, 'r');
        NoDevEnvUtil::assertNotFalse($fileHandle, compact('fileHandle'));

        NoDevEnvUtil::runCodeAndCleanUp(
            function () use ($filePath, $fileHandle, &$result) {
                while (($line = fgets($fileHandle)) !== false) {
                    if (str_ends_with($line, "\r\n")) {
                        $line = substr($line, 0, strlen($line) - 2);
                    } elseif (str_ends_with($line, "\r") || str_ends_with($line, "\n")) {
                        $line = substr($line, 0, strlen($line) - 1);
                    }

                    if (($line === '') || str_starts_with($line, '#')) {
                        continue;
                    }

                    if (str_contains($line, '*')) {
                        if (str_ends_with($line, '/*')) {
                            $result['directory_names'][] = substr($line, 0, strlen($line) - 2);
                        } elseif (str_ends_with($line, '*')) {
                            $result['name_prefixes'][] = substr($line, 0, strlen($line) - 1);
                        }
                    } else {
                        if (str_ends_with($line, '/')) {
                            $result['directory_names'][] = substr($line, 0, strlen($line) - 1);
                        } else {
                            $result['names'][] = $line;
                        }
                    }
                }
                NoDevEnvUtil::assert(feof($fileHandle), 'feof($fileHandle)' . ' ; ' . json_encode(compact('filePath')));
            },
            cleanUp: function () use ($filePath, $fileHandle): void {
                NoDevEnvUtil::assertNotFalse(fclose($fileHandle), compact('filePath'));
            },
        );
    }

    /**
     * @param EntriesToExcludeFromRepoTempCopy $entriesToExclude
     */
    private static function shouldEntryBeExcludedFromRepoTempCopy(string $entryNameToCheck, bool $isDir, array $entriesToExclude): bool
    {
        foreach ($entriesToExclude['names'] as $entryToExclude) {
            if ($entryNameToCheck === $entryToExclude) {
                return true;
            }
        }

        foreach ($entriesToExclude['name_prefixes'] as $entryToExcludePrefix) {
            if (str_starts_with($entryNameToCheck, $entryToExcludePrefix)) {
                return true;
            }
        }

        if ($isDir) {
            foreach ($entriesToExclude['directory_names'] as $entryToExclude) {
                if ($entryNameToCheck === $entryToExclude) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function minimizeTempDevEnvComposerJson(string $composerJsonFilePath): void
    {
        NoDevEnvUtil::log("Minimizing $composerJsonFilePath");

        $fileContents = NoDevEnvUtil::getFileContents($composerJsonFilePath);
        $jsonDecoded = json_decode($fileContents, /* assoc: */ true);
        NoDevEnvUtil::assertIsArray($jsonDecoded, compact('jsonDecoded'));
        $resultArray = $jsonDecoded;

        // Remove all "open-telemetry/*" "require"
        $srcRequireSection = $jsonDecoded[NoDevEnvUtil::COMPOSER_JSON_REQUIRE_KEY];
        NoDevEnvUtil::assertIsArray($srcRequireSection, compact('srcRequireSection'));
        $dstRequireSection =& $resultArray[NoDevEnvUtil::COMPOSER_JSON_REQUIRE_KEY];
        NoDevEnvUtil::assertIsArray($dstRequireSection, compact('dstRequireSection'));
        foreach ($srcRequireSection as $packageFullName => $_) {
            [$packageVendor, $packageName] = NoDevEnvUtil::splitDependencyFullName($packageFullName);
            if ($packageVendor === 'open-telemetry' && str_starts_with($packageName, 'opentelemetry-auto-')) {
                unset($dstRequireSection[$packageFullName]);
            }
        }

        // Remove all "ext-*" in "require-dev" section
        $srcRequireDevSection = $jsonDecoded[NoDevEnvUtil::COMPOSER_JSON_REQUIRE_DEV_KEY];
        NoDevEnvUtil::assertIsArray($srcRequireDevSection, compact('srcRequireDevSection'));
        $dstRequireDevSection =& $resultArray[NoDevEnvUtil::COMPOSER_JSON_REQUIRE_DEV_KEY];
        NoDevEnvUtil::assertIsArray($dstRequireDevSection, compact('dstRequireDevSection'));
        foreach ($srcRequireDevSection as $dependencyFullName => $_) {
            $packageVendor = NoDevEnvUtil::splitDependencyFullName($dependencyFullName)[0];
            if (str_starts_with($packageVendor, 'ext-')) {
                unset($dstRequireDevSection[$dependencyFullName]);
            }
        }

        foreach (['provide', 'scripts'] as $topSectionName) {
            if (array_key_exists($topSectionName, $resultArray)) {
                unset($resultArray[$topSectionName]);
            }
        }

        $resultArrayEncoded = JsonUtil::encode($resultArray, prettyPrint: true);
        NoDevEnvUtil::putFileContents($composerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        NoDevEnvUtil::listFileContents($composerJsonFilePath);
    }

    private static function installVendorOnRepoTempCopy(string $repoTempCopyRootDir): void
    {
        NoDevEnvUtil::log("Installing vendor at $repoTempCopyRootDir");

        NoDevEnvUtil::changeCurrentDirectoryRunCodeAndRestore(
            $repoTempCopyRootDir,
            function (): void {
                NoDevEnvUtil::log('Current directory: ' . NoDevEnvUtil::getCurrentDirectory());
                NoDevEnvUtil::listDirectoryContents(NoDevEnvUtil::getCurrentDirectory());
                NoDevEnvUtil::listFileContents(NoDevEnvUtil::getCurrentDirectory() . DIRECTORY_SEPARATOR . NoDevEnvUtil::COMPOSER_JSON_FILE_NAME);
                NoDevEnvUtil::execShellCommand('composer --no-interaction install');
            }
        );

        NoDevEnvUtil::listDirectoryContents($repoTempCopyRootDir . DIRECTORY_SEPARATOR . 'vendor');
    }
}
