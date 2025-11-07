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

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\BoolUtil;
use RuntimeException;

/**
 * This class is used in scripts section of composer.json
 *
 * @noinspection PhpUnused
 */
final class AdaptPackagesToPhp81
{
    private const AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES = [
        'open-telemetry/opentelemetry-auto-curl',
        'open-telemetry/opentelemetry-auto-mysqli',
        'open-telemetry/opentelemetry-auto-pdo',
        'open-telemetry/opentelemetry-auto-postgresql',
    ];

    private const COMPOSER_JSON_VERSION_KEY = 'version';
    private const COMPOSER_JSON_REPOSITORIES_KEY = 'repositories';
    private const COMPOSER_JSON_PHP_KEY = 'php';

    /**
     * Make sure the following value is in sync with the rest of locations where it's used (see elastic_otel_php_packages_adapted_to_PHP_81_rel_path in <repo root>/tools/shared.sh)
     *
     * The path is relative to repo root
     */
    private const PACKAGES_ADAPTED_TO_PHP_81_REL_PATH = 'build/packages_adapted_to_PHP_81';

    /**
     * The path is relative to repo root
     */
    private const COMPOSER_JSON_LOCK_ADAPTED_TO_PHP_81_REL_PATH_NO_EXT = 'build/composer_adapted_to_PHP_81';

    /**
     * @param list<string> $cmdLineArgs
     */
    public static function adaptComposerJsonDownloadAndAdaptPackages(array $cmdLineArgs): void
    {
        ComposerScripts::assertCount(2, $cmdLineArgs);
        $repoRootStageDir = $cmdLineArgs[0];
        $adaptedRepoRootComposerJsonDstFile = $cmdLineArgs[1];
        ComposerScripts::assertDirectoryExists($repoRootStageDir);
        ComposerScripts::assertFileDoesNotExist($adaptedRepoRootComposerJsonDstFile);

        $repoRootComposerJsonSrcFile = ComposerScripts::partsToPath($repoRootStageDir, ComposerScripts::COMPOSER_JSON_FILE_NAME);
        $adaptedPackagesDir = ComposerScripts::partsToPath($repoRootStageDir, ComposerScripts::adaptUnixDirectorySeparators(self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
        ComposerScripts::createDirectory($adaptedPackagesDir);
        $packagesNameToVersion = self::downloadAndAdaptPackages($repoRootComposerJsonSrcFile, $adaptedPackagesDir);

        ComposerScripts::copyFile($repoRootComposerJsonSrcFile, $adaptedRepoRootComposerJsonDstFile);
        self::adaptRepoRootComposerJson($packagesNameToVersion, $adaptedRepoRootComposerJsonDstFile);
    }

    public static function isCurrentPhpVersion81(): bool
    {
        // If the current PHP version is 8.1.*
        // @phpstan-ignore-next-line
        return (80100 <= PHP_VERSION_ID) && (PHP_VERSION_ID < 80200);
    }

    public static function installSelectJsonLock(string $envKind): void
    {
        $repoRootPath = ComposerScripts::getRepoRootPath();
        $tempDirAboveAdaptedPackagesName = ComposerScripts::getFirstDirFromUnixPath(self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH);
        $tempDirAboveAdaptedPackagesPath = ComposerScripts::partsToPath($repoRootPath, $tempDirAboveAdaptedPackagesName);
        $tempDirAboveAdaptedPackageExisted = file_exists($tempDirAboveAdaptedPackagesPath);
        $adaptedPackagesDir = ComposerScripts::partsToPath($repoRootPath, ComposerScripts::adaptUnixDirectorySeparators(self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
        if (file_exists($adaptedPackagesDir)) {
            ComposerScripts::deleteDirectoryContents($adaptedPackagesDir);
        } else {
            ComposerScripts::createTempDirectory($adaptedPackagesDir);
        }
        ComposerScripts::runCodeAndCleanUp(
            function () use ($repoRootPath, $envKind, $adaptedPackagesDir): void {
                $repoRootComposerJson = ComposerScripts::partsToPath($repoRootPath, ComposerScripts::COMPOSER_JSON_FILE_NAME);
                self::downloadAndAdaptPackages($repoRootComposerJson, $adaptedPackagesDir);
                $generatedComposerJsonPath = ComposerScripts::buildToGeneratedFileFullPath($repoRootPath, ComposerScripts::buildGeneratedComposerJsonFileNameForCurrentPhpVersion($envKind));
                $composerJsonToUseRelPath = self::COMPOSER_JSON_LOCK_ADAPTED_TO_PHP_81_REL_PATH_NO_EXT . '.json';
                $composerJsonToUsePath = ComposerScripts::partsToPath($repoRootPath, $composerJsonToUseRelPath);
                ComposerScripts::copyFile($generatedComposerJsonPath, $composerJsonToUsePath);
                $generatedComposerLockPath = ComposerScripts::buildToGeneratedFileFullPath($repoRootPath, ComposerScripts::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
                $composerLockToUsePath = ComposerScripts::partsToPath($repoRootPath, self::COMPOSER_JSON_LOCK_ADAPTED_TO_PHP_81_REL_PATH_NO_EXT . '.lock');
                ComposerScripts::copyFile($generatedComposerLockPath, $composerLockToUsePath);
                ComposerScripts::verifyThatComposerJsonAndLockAreInSync(composerJsonFilePath: $composerJsonToUseRelPath);
                ComposerScripts::execComposerInstallShellCommand(
                    withDev: ComposerScripts::convertEnvKindToWithDev($envKind),
                    envVars: [
                        ComposerScripts::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true),
                        ComposerScripts::COMPOSER_ENV_VAR_NAME => $composerJsonToUseRelPath,
                    ],
                );
            },
            cleanUp: function () use ($adaptedPackagesDir, $tempDirAboveAdaptedPackageExisted, $tempDirAboveAdaptedPackagesPath): void {
                if ($tempDirAboveAdaptedPackageExisted) {
                    ComposerScripts::deleteTempDirectory($adaptedPackagesDir);
                } else {
                    ComposerScripts::deleteTempDirectory($tempDirAboveAdaptedPackagesPath);
                }
            },
        );
    }

    /**
     * @phpstan-return array<string, string>
     */
    private static function downloadAndAdaptPackages(string $repoRootComposerJsonSrcFile, string $adaptedPackagesDir): array
    {
        $packagesNameToVersion = ComposerScripts::runCodeOnTempDir(
            ComposerScripts::fqClassNameToShort(__CLASS__) . '_work_',
            /**
             * @phpstan-return array<string, string>
             */
            function (string $adaptPackagesWorkDir) use ($repoRootComposerJsonSrcFile, $adaptedPackagesDir): array {
                $minimalComposerJsonFilePath = ComposerScripts::partsToPath($adaptPackagesWorkDir, ComposerScripts::COMPOSER_JSON_FILE_NAME);
                ComposerScripts::copyFile($repoRootComposerJsonSrcFile, $minimalComposerJsonFilePath);
                $packagesNameToVersion = self::reduceComposerJsonToPackagesToAdaptOnly($minimalComposerJsonFilePath);
                ComposerScripts::changeCurrentDirectoryRunCodeAndRestore(
                    $adaptPackagesWorkDir,
                    fn() => ComposerScripts::execComposerInstallShellCommand(withDev: false, additionalArgs: '--ignore-platform-req=php --no-plugins --no-scripts'),
                );
                self::adaptPackages($packagesNameToVersion, $adaptPackagesWorkDir, $adaptedPackagesDir);
                return $packagesNameToVersion;
            }
        );
        ComposerScripts::listDirectoryContents($adaptedPackagesDir, recursiveDepth: 1);
        return $packagesNameToVersion;
    }

    /**
     * @phpstan-return array<string, string>
     */
    private static function reduceComposerJsonToPackagesToAdaptOnly(string $minimalComposerJsonFilePath): array
    {
        $fileContents = ComposerScripts::getFileContents($minimalComposerJsonFilePath);
        $fileContentsJsonDecoded = ComposerScripts::assertIsArray(ComposerScripts::decodeJson($fileContents, asAssocArray: true));
        // Keep only "require" top key
        $resultArray = array_filter($fileContentsJsonDecoded, fn ($key) => $key === ComposerScripts::COMPOSER_JSON_REQUIRE_KEY, ARRAY_FILTER_USE_KEY);
        ComposerScripts::assertCount(1, $resultArray);
        ComposerScripts::assertArrayHasKey(ComposerScripts::COMPOSER_JSON_REQUIRE_KEY, $resultArray); // @phpstan-ignore staticMethod.impossibleType
        // Keep only packages instrumenting native functions
        $requireSection = ComposerScripts::assertIsArray($resultArray[ComposerScripts::COMPOSER_JSON_REQUIRE_KEY]);
        $packageNameToVersion = array_filter($requireSection, fn($package) => in_array($package, self::AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES), ARRAY_FILTER_USE_KEY);
        /** @var array<string, string> $packageNameToVersion */
        $resultArray[ComposerScripts::COMPOSER_JSON_REQUIRE_KEY] = $packageNameToVersion;
        $resultArrayEncoded = ComposerScripts::encodeJson($resultArray, prettyPrint: true);
        ComposerScripts::putFileContents($minimalComposerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        return $packageNameToVersion;
    }

    /**
     * @param array<string, string> $packagesNameToVersion
     */
    private static function adaptPackages(array $packagesNameToVersion, string $adaptPackagesWorkDir, string $adaptedPackagesDir): void
    {
        foreach ($packagesNameToVersion as $packageFullName => $packageVersion) {
            [$packageVendor, $packageName] = ComposerScripts::splitDependencyFullName($packageFullName);
            $packageSrcDir = ComposerScripts::partsToPath($adaptPackagesWorkDir, 'vendor', $packageVendor, $packageName);
            ComposerScripts::assertDirectoryExists($packageSrcDir);
            $packageDstDir = ComposerScripts::partsToPath($adaptedPackagesDir, $packageVendor, $packageName);
            ComposerScripts::createDirectory($packageDstDir);
            ComposerScripts::copyDirectoryContents($packageSrcDir, $packageDstDir);
            $composerJsonFilePath = ComposerScripts::partsToPath($packageDstDir, ComposerScripts::COMPOSER_JSON_FILE_NAME);
            ComposerScripts::assertFileExists($composerJsonFilePath);
            self::adaptPackageComposerJson($composerJsonFilePath, $packageVersion);
        }
    }

    private static function adaptPackageComposerJson(string $composerJsonFilePath, string $packageVersion): void
    {
        $fileContents = ComposerScripts::getFileContents($composerJsonFilePath);
        $jsonDecoded = ComposerScripts::assertIsArray(ComposerScripts::decodeJson($fileContents, asAssocArray: true));
        $resultArray = $jsonDecoded;
        if (ArrayUtil::getValueIfKeyExists(self::COMPOSER_JSON_VERSION_KEY, $jsonDecoded, /* out */ $alreadyPresentVersion) && ($alreadyPresentVersion !== $packageVersion)) {
            ComposerScripts::assertIsString($alreadyPresentVersion);
            throw new RuntimeException(
                "Package's composer.json already has version but it's different from the one in root composer.json"
                . "; version in package's composer.json: $alreadyPresentVersion ; version in the root composer.json: $packageVersion; repoRootComposerJsonSrcFile: $composerJsonFilePath",
            );
        }
        ComposerScripts::assertArrayHasKey(ComposerScripts::COMPOSER_JSON_REQUIRE_KEY, $resultArray);
        $requireSectionRef =& $resultArray[ComposerScripts::COMPOSER_JSON_REQUIRE_KEY];
        ComposerScripts::assertIsArray($requireSectionRef);
        ComposerScripts::assertArrayHasKey(self::COMPOSER_JSON_PHP_KEY, $requireSectionRef);
        $requireSectionRef[self::COMPOSER_JSON_PHP_KEY] = '8.1.*';

        $resultArrayEncoded = ComposerScripts::encodeJson($resultArray, prettyPrint: true);
        ComposerScripts::putFileContents($composerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        ComposerScripts::listFileContents($composerJsonFilePath);
    }

    /**
     * @param array<string, string> $packagesNameToVersion
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function adaptRepoRootComposerJson(array $packagesNameToVersion, string $adaptedRepoRootComposerJsonDstFile): void
    {
        $fileContents = ComposerScripts::getFileContents($adaptedRepoRootComposerJsonDstFile);
        $jsonDecoded = ComposerScripts::assertIsArray(ComposerScripts::decodeJson($fileContents, asAssocArray: true));
        ComposerScripts::assertArrayNotHasKey(self::COMPOSER_JSON_REPOSITORIES_KEY, $jsonDecoded);
        $repositoriesVal = [];
        foreach ($packagesNameToVersion as $packageFullName => $packageVersion) {
            [$packageVendor, $packageName] = ComposerScripts::splitDependencyFullName($packageFullName);
            $repositoriesVal[] = [
                'type' => 'path',
                'url' => self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH . '/' . $packageVendor . '/' . $packageName,
                'options' => [
                    'versions' => [
                        ($packageVendor . '/' . $packageName) => $packageVersion,
                    ],
                    'symlink' => false,
                ],
            ];
        }

        $resultArray = $jsonDecoded;
        $resultArray[self::COMPOSER_JSON_REPOSITORIES_KEY] = $repositoriesVal;
        $resultArrayEncoded = ComposerScripts::encodeJson($resultArray, prettyPrint: true);
        ComposerScripts::putFileContents($adaptedRepoRootComposerJsonDstFile, $resultArrayEncoded . PHP_EOL);
        ComposerScripts::listFileContents($adaptedRepoRootComposerJsonDstFile);
    }
}
