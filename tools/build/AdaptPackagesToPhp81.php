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
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    private const AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES = [
        'open-telemetry/opentelemetry-auto-curl',
        'open-telemetry/opentelemetry-auto-mysqli',
        'open-telemetry/opentelemetry-auto-pdo',
        'open-telemetry/opentelemetry-auto-postgresql',
    ];

    private const COMPOSER_IGNORE_PHP_REQ_CMD_OPT = '--ignore-platform-req=php';

    private const COMPOSER_JSON_REQUIRE_KEY = 'require';
    private const COMPOSER_JSON_VERSION_KEY = 'version';
    private const COMPOSER_JSON_REPOSITORIES_KEY = 'repositories';
    private const COMPOSER_JSON_PHP_KEY = 'php';

    private const COMPOSER_HOME_ENV_VAR_NAME = 'COMPOSER_HOME';
    private const COMPOSER_HOME_CONFIG_JSON_FILE_NAME = 'config.json';

    /**
     * Make sure the following value is in sync with the rest of locations where it's used (see elastic_otel_php_packages_adapted_to_PHP_81_rel_path in <repo root>/tools/shared.sh)
     *
     * The path is relative to repo root
     */
    private const PACKAGES_ADAPTED_TO_PHP_81_REL_PATH = 'build/adapted_to_PHP_81/packages';

    /**
     * Make sure the following value is in sync with the rest of locations where it's used
     * (see elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path in <repo root>/tools/shared.sh)
     * *
     * The path is relative to repo root
     */
    private const COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH = 'build/adapted_to_PHP_81/composer_home';

    public static function isCurrentPhpVersion81(): bool
    {
        // If the current PHP version is 8.1.*
        // @phpstan-ignore-next-line
        return (80100 <= PHP_VERSION_ID) && (PHP_VERSION_ID < 80200);
    }

    public static function downloadAdaptPackagesAndGenConfig(): void
    {
        BuildToolsUtil::runCmdLineImpl(
            function (): void {
                self::downloadAdaptPackagesAndGenConfigImpl(BuildToolsFileUtil::getCurrentDirectory());
            }
        );
    }

    public static function downloadAdaptPackagesGenConfigAndInstall(bool $withDev): void
    {
        $repoRootDir = BuildToolsFileUtil::getCurrentDirectory();
        self::downloadAdaptPackagesAndGenConfigImpl($repoRootDir);
        $composerHomeDir = BuildToolsFileUtil::partsToPath($repoRootDir, BuildToolsFileUtil::adaptUnixDirectorySeparators(self::COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
        ComposerUtil::execComposerInstallShellCommand(
            withDev: $withDev,
            envVars: [
                ComposerUtil::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true),
                self::COMPOSER_HOME_ENV_VAR_NAME => $composerHomeDir,
            ],
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
        return BuildToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: BuildToolsUtil::fqClassNameToShort(__CLASS__) . '_work_',
            /**
             * @phpstan-return array<string, string>
             */
            code: function (string $workDir) use ($repoRootDir): array {
                $adaptedPackagesDir = BuildToolsFileUtil::partsToPath($repoRootDir, BuildToolsFileUtil::adaptUnixDirectorySeparators(self::PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
                BuildToolsFileUtil::createTempDirectory($adaptedPackagesDir);
                $minimalComposerJsonFilePath = BuildToolsFileUtil::partsToPath($workDir, ComposerUtil::COMPOSER_JSON_FILE_NAME);
                $repoRootComposerJsonSrcFile = BuildToolsFileUtil::partsToPath($repoRootDir, ComposerUtil::COMPOSER_JSON_FILE_NAME);
                BuildToolsFileUtil::copyFile($repoRootComposerJsonSrcFile, $minimalComposerJsonFilePath);
                $packagesNameToVersion = self::reduceComposerJsonToPackagesToAdaptOnly($minimalComposerJsonFilePath);
                BuildToolsUtil::changeCurrentDirectoryRunCodeAndRestore(
                    $workDir,
                    fn() => ComposerUtil::execComposerInstallShellCommand(withDev: false, additionalArgs: self::COMPOSER_IGNORE_PHP_REQ_CMD_OPT . ' --no-plugins --no-scripts'),
                );
                self::adaptPackages($packagesNameToVersion, $workDir, $adaptedPackagesDir);
                BuildToolsFileUtil::listDirectoryContents($adaptedPackagesDir, recursiveDepth: 1);
                return $packagesNameToVersion;
            },
        );
    }

    /**
     * @phpstan-return array<string, string>
     */
    private static function reduceComposerJsonToPackagesToAdaptOnly(string $minimalComposerJsonFilePath): array
    {
        $fileContents = BuildToolsFileUtil::getFileContents($minimalComposerJsonFilePath);
        $fileContentsJsonDecoded = self::assertIsArray(BuildToolsUtil::decodeJson($fileContents, asAssocArray: true));
        // Keep only "require" top key
        $resultArray = array_filter($fileContentsJsonDecoded, fn ($key) => $key === self::COMPOSER_JSON_REQUIRE_KEY, ARRAY_FILTER_USE_KEY);
        self::assertCount(1, $resultArray);
        self::assertArrayHasKey(self::COMPOSER_JSON_REQUIRE_KEY, $resultArray);
        // Keep only packages instrumenting native functions
        $requireSection = self::assertIsArray($resultArray[self::COMPOSER_JSON_REQUIRE_KEY]);
        $packageNameToVersion = array_filter($requireSection, fn($package) => in_array($package, self::AUTO_INSTRUM_NATIVE_FUNCS_PACKAGES), ARRAY_FILTER_USE_KEY);
        /** @var array<string, string> $packageNameToVersion */
        $resultArray[self::COMPOSER_JSON_REQUIRE_KEY] = $packageNameToVersion;
        $resultArrayEncoded = BuildToolsUtil::encodeJson($resultArray, prettyPrint: true);
        BuildToolsFileUtil::putFileContents($minimalComposerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        return $packageNameToVersion;
    }

    /**
     * @param array<string, string> $packagesNameToVersion
     */
    private static function adaptPackages(array $packagesNameToVersion, string $adaptPackagesWorkDir, string $adaptedPackagesDir): void
    {
        foreach ($packagesNameToVersion as $packageFullName => $packageVersion) {
            [$packageVendor, $packageName] = self::splitDependencyFullName($packageFullName);
            $packageSrcDir = BuildToolsFileUtil::partsToPath($adaptPackagesWorkDir, 'vendor', $packageVendor, $packageName);
            self::assertDirectoryExists($packageSrcDir);
            $packageDstDir = BuildToolsFileUtil::partsToPath($adaptedPackagesDir, $packageVendor, $packageName);
            BuildToolsFileUtil::createDirectory($packageDstDir);
            BuildToolsFileUtil::copyDirectoryContents($packageSrcDir, $packageDstDir);
            $composerJsonFilePath = BuildToolsFileUtil::partsToPath($packageDstDir, ComposerUtil::COMPOSER_JSON_FILE_NAME);
            self::assertFileExists($composerJsonFilePath);
            self::adaptPackageComposerJson($composerJsonFilePath, $packageVersion);
        }
    }

    private static function adaptPackageComposerJson(string $composerJsonFilePath, string $packageVersion): void
    {
        $fileContents = BuildToolsFileUtil::getFileContents($composerJsonFilePath);
        $jsonDecoded = self::assertIsArray(BuildToolsUtil::decodeJson($fileContents, asAssocArray: true));
        $resultArray = $jsonDecoded;
        if (ArrayUtil::getValueIfKeyExists(self::COMPOSER_JSON_VERSION_KEY, $jsonDecoded, /* out */ $alreadyPresentVersion) && ($alreadyPresentVersion !== $packageVersion)) {
            self::assertIsString($alreadyPresentVersion);
            throw new RuntimeException(
                "Package's composer.json already has version but it's different from the one in root composer.json"
                . "; version in package's composer.json: $alreadyPresentVersion ; version in the root composer.json: $packageVersion; repoRootComposerJsonSrcFile: $composerJsonFilePath",
            );
        }
        self::assertArrayHasKey(self::COMPOSER_JSON_REQUIRE_KEY, $resultArray);
        $requireSectionRef =& $resultArray[self::COMPOSER_JSON_REQUIRE_KEY];
        self::assertIsArray($requireSectionRef);
        self::assertArrayHasKey(self::COMPOSER_JSON_PHP_KEY, $requireSectionRef);
        $requireSectionRef[self::COMPOSER_JSON_PHP_KEY] = '8.1.*';

        $resultArrayEncoded = BuildToolsUtil::encodeJson($resultArray, prettyPrint: true);
        BuildToolsFileUtil::putFileContents($composerJsonFilePath, $resultArrayEncoded . PHP_EOL);
        BuildToolsFileUtil::listFileContents($composerJsonFilePath);
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

        $resultArrayEncoded = BuildToolsUtil::encodeJson([self::COMPOSER_JSON_REPOSITORIES_KEY => $repositoriesVal], prettyPrint: true);
        $composerHomeDir = BuildToolsFileUtil::partsToPath($repoRootDir, BuildToolsFileUtil::adaptUnixDirectorySeparators(self::COMPOSER_HOME_FOR_PACKAGES_ADAPTED_TO_PHP_81_REL_PATH));
        BuildToolsFileUtil::createTempDirectory($composerHomeDir);
        $composerHomeConfigJsonFilePath = BuildToolsFileUtil::partsToPath($composerHomeDir, self::COMPOSER_HOME_CONFIG_JSON_FILE_NAME);
        BuildToolsFileUtil::putFileContents($composerHomeConfigJsonFilePath, $resultArrayEncoded . PHP_EOL);
        BuildToolsFileUtil::listFileContents($composerHomeConfigJsonFilePath);
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
     * Must be defined in class using BuildToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
