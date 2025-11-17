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

namespace ElasticOTelTools\build;

use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsUtil;
use RuntimeException;

/**
 * @phpstan-import-type EnvVars from ToolsUtil
 */
final class AdaptPhpDepsTo81
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    private const AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES = [
        'open-telemetry/opentelemetry-auto-curl',
        'open-telemetry/opentelemetry-auto-mysqli',
        'open-telemetry/opentelemetry-auto-pdo',
        'open-telemetry/opentelemetry-auto-postgresql',
    ];

    private const COMPOSER_IGNORE_PHP_REQ_CMD_OPT = '--ignore-platform-req=php';

    private const COMPOSER_JSON_VERSION_KEY = 'version';
    private const COMPOSER_JSON_REPOSITORIES_KEY = 'repositories';

    private const ADAPTED_TO_PHP_81_FIRST_DIR_REL_PATH = 'build';
    private const ADAPTED_TO_PHP_81_LAST_DIR_REL_PATH = self::ADAPTED_TO_PHP_81_FIRST_DIR_REL_PATH . '/adapted_to_PHP_81'; // 'build/adapted_to_PHP_81'

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_packages_adapted_to_PHP_81_rel_path in <repo root>/tools/shared.sh)
     *
     * The path is relative to repo root
     */
    private const PACKAGES_ADAPTED_TO_PHP_81_REL_PATH = self::ADAPTED_TO_PHP_81_LAST_DIR_REL_PATH . '/packages'; // 'build/adapted_to_PHP_81/packages'

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined
     * (see elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path in <repo root>/tools/shared.sh)
     * *
     * The path is relative to repo root
     */
    private const COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH = self::ADAPTED_TO_PHP_81_LAST_DIR_REL_PATH . '/composer_home'; // 'build/adapted_to_PHP_81/composer_home'

    public static function isCurrentPhpVersion81(): bool
    {
        // If the current PHP version is 8.1.*
        // @phpstan-ignore-next-line
        return (80100 <= PHP_VERSION_ID) && (PHP_VERSION_ID < 80200);
    }

    public static function downloadAdaptPackagesAndGenConfig(): void
    {
        ToolsUtil::runCmdLineImpl(
            __METHOD__,
            function (): void {
                self::downloadAdaptPackagesAndGenConfigImpl(ToolsUtil::getCurrentDirectory());
            }
        );
    }

    public static function downloadAdaptPackagesGenConfigAndInstallProd(string $repoRootDir): void
    {
        InstallPhpDeps::installInTempAndCopy(
            PhpDepsEnvKind::prod,
            $repoRootDir,
            /**
             * @phpstan-return EnvVars
             */
            preProcess: function (string $tempRepoDir): array {
                self::downloadAdaptPackagesAndGenConfigImpl($tempRepoDir);
                $composerHomeDir = ToolsUtil::partsToPath($tempRepoDir, ToolsUtil::adaptUnixDirectorySeparators(self::COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
                return [ComposerUtil::HOME_ENV_VAR_NAME => $composerHomeDir];
            }
        );
    }

    private static function downloadAdaptPackagesAndGenConfigImpl(string $repoRootDir): void
    {
        $packagesNameToVersion = self::downloadAndAdaptPackages($repoRootDir);
        self::generateComposerGlobalConfig($packagesNameToVersion, $repoRootDir);
    }

    /**
     * @phpstan-return array<string, string>
     */
    private static function downloadAndAdaptPackages(string $repoRootDir): array
    {
        return ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . '_',
            /**
             * @phpstan-return array<string, string>
             */
            code: function (string $workDir) use ($repoRootDir): array {
                $adaptedPackagesDir = ToolsUtil::partsToPath($repoRootDir, ToolsUtil::adaptUnixDirectorySeparators(self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
                ToolsUtil::createTempDirectory($adaptedPackagesDir);
                $prodComposerJsonPath = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME);
                $minimalComposerJsonPath = ToolsUtil::partsToPath($workDir, ComposerUtil::JSON_FILE_NAME);
                ToolsUtil::copyFile($prodComposerJsonPath, $minimalComposerJsonPath);
                $packagesNameToVersion = self::reduceComposerJsonToPackagesToAdaptOnly($minimalComposerJsonPath);
                ToolsUtil::listFileContents($minimalComposerJsonPath);
                ComposerUtil::execInstall(withDev: false, additionalArgs: self::COMPOSER_IGNORE_PHP_REQ_CMD_OPT . ' --no-plugins --no-scripts');
                self::adaptPackages($packagesNameToVersion, $workDir, $adaptedPackagesDir);
                ToolsUtil::listDirectoryContents($adaptedPackagesDir, recursiveDepth: 1);
                return $packagesNameToVersion;
            },
        );
    }

    /**
     * @phpstan-return array<string, string>
     */
    private static function reduceComposerJsonToPackagesToAdaptOnly(string $minimalComposerJsonFilePath): array
    {
        $fileContents = ToolsUtil::getFileContents($minimalComposerJsonFilePath);
        self::logDebug(__LINE__, __METHOD__, 'Entered; fileContents: ' . $fileContents);
        $fileContentsJsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($fileContents));
        // Keep only "require" top key
        $resultArray = array_filter($fileContentsJsonDecoded, fn ($key) => $key === ComposerUtil::JSON_REQUIRE_KEY, ARRAY_FILTER_USE_KEY);
        self::assertCount(1, $resultArray);
        self::assertArrayHasKey(ComposerUtil::JSON_REQUIRE_KEY, $resultArray);
        self::logDebug(__LINE__, __METHOD__, 'After keeping only "require" top key', compact('resultArray'));
        // Keep only packages instrumenting native functions
        $requireSection = self::assertIsArray($resultArray[ComposerUtil::JSON_REQUIRE_KEY]);
        $packageNameToVersion = array_filter($requireSection, fn($package) => in_array($package, self::AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES), ARRAY_FILTER_USE_KEY);
        /** @var array<string, string> $packageNameToVersion */
        $resultArray[ComposerUtil::JSON_REQUIRE_KEY] = $packageNameToVersion;
        $resultArrayEncoded = ToolsUtil::encodeJson($resultArray, prettyPrint: true);
        ToolsUtil::putFileContents($minimalComposerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        return $packageNameToVersion;
    }

    /**
     * @param array<string, string> $packagesNameToVersion
     */
    private static function adaptPackages(array $packagesNameToVersion, string $adaptPackagesWorkDir, string $adaptedPackagesDir): void
    {
        foreach ($packagesNameToVersion as $packageFullName => $packageVersion) {
            [$packageVendor, $packageName] = self::splitDependencyFullName($packageFullName);
            $packageSrcDir = ToolsUtil::partsToPath($adaptPackagesWorkDir, ComposerUtil::VENDOR_DIR_NAME, $packageVendor, $packageName);
            self::assertDirectoryExists($packageSrcDir);
            $packageDstDir = ToolsUtil::partsToPath($adaptedPackagesDir, $packageVendor, $packageName);
            ToolsUtil::createDirectory($packageDstDir);
            ToolsUtil::copyDirectoryContents($packageSrcDir, $packageDstDir);
            $composerJsonFilePath = ToolsUtil::partsToPath($packageDstDir, ComposerUtil::JSON_FILE_NAME);
            self::assertFileExists($composerJsonFilePath);
            self::adaptPackageComposerJson($composerJsonFilePath, $packageVersion);
        }
    }

    private static function adaptPackageComposerJson(string $composerJsonFilePath, string $packageVersion): void
    {
        $fileContents = ToolsUtil::getFileContents($composerJsonFilePath);
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($fileContents));
        $resultArray = $jsonDecoded;
        if (ArrayUtil::getValueIfKeyExists(self::COMPOSER_JSON_VERSION_KEY, $jsonDecoded, /* out */ $alreadyPresentVersion) && ($alreadyPresentVersion !== $packageVersion)) {
            self::assertIsString($alreadyPresentVersion);
            throw new RuntimeException(
                "Package's composer.json already has version but it's different from the one in root composer.json"
                . "; version in package's composer.json: $alreadyPresentVersion ; version in the root composer.json: $packageVersion; repoRootComposerJsonSrcFile: $composerJsonFilePath",
            );
        }
        self::assertArrayHasKey(ComposerUtil::JSON_REQUIRE_KEY, $resultArray);
        $requireSectionRef =& $resultArray[ComposerUtil::JSON_REQUIRE_KEY];
        self::assertIsArray($requireSectionRef);
        self::assertArrayHasKey(ComposerUtil::JSON_PHP_KEY, $requireSectionRef);
        $requireSectionRef[ComposerUtil::JSON_PHP_KEY] = '8.1.*';

        $resultArrayEncoded = ToolsUtil::encodeJson($resultArray, prettyPrint: true);
        ToolsUtil::putFileContents($composerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        ToolsUtil::listFileContents($composerJsonFilePath);
    }

    /**
     * @param array<string, string> $packagesNameToVersion
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function generateComposerGlobalConfig(array $packagesNameToVersion, string $repoRootDir): void
    {
        $repositoriesVal = [];
        foreach ($packagesNameToVersion as $packageFullName => $packageVersion) {
            [$packageVendor, $packageName] = self::splitDependencyFullName($packageFullName);
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

        $resultArrayEncoded = ToolsUtil::encodeJson([self::COMPOSER_JSON_REPOSITORIES_KEY => $repositoriesVal], prettyPrint: true);
        $composerHomeDir = ToolsUtil::partsToPath($repoRootDir, ToolsUtil::adaptUnixDirectorySeparators(self::COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
        ToolsUtil::createTempDirectory($composerHomeDir);
        $composerHomeConfigJsonFilePath = ToolsUtil::partsToPath($composerHomeDir, ComposerUtil::HOME_CONFIG_JSON_FILE_NAME);
        ToolsUtil::putFileContents($composerHomeConfigJsonFilePath, $resultArrayEncoded . PHP_EOL);
        ToolsUtil::listFileContents($composerHomeConfigJsonFilePath);
    }

    /**
     * @phpstan-return list{string, string}
     */
    public static function splitDependencyFullName(string $dependencyFullName): array
    {
        self::assertStringNotEmpty($dependencyFullName, compact('dependencyFullName'));
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

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
