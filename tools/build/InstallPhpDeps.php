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

use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsUtil;

/**
 * @phpstan-import-type EnvVars from ToolsUtil
 */
final class InstallPhpDeps
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_generated_composer_lock_files_dir_name in <repo root>/tools/shared.sh)
     */
    private const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_generated_files_copy_of_composer_json_file_name in <repo root>/tools/shared.sh)
     */
    private const COPY_OF_COMPOSER_JSON_FILE_NAME = 'copy_of_composer.json';

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_vendor_prod_dir_name in <repo root>/tools/shared.sh)
     */
    public const VENDOR_PROD_DIR_NAME = 'vendor_prod';

    public static function verifyGeneratedComposerLockFiles(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function (): void {
                $repoRootDir = ToolsUtil::getCurrentDirectory();
                $repoRootComposerJsonPath = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME);
                $generatedFilesComposerJsonPath = self::buildToGeneratedFileFullPath($repoRootDir, self::COPY_OF_COMPOSER_JSON_FILE_NAME);
                self::assertFilesHaveSameContent($repoRootComposerJsonPath, $generatedFilesComposerJsonPath);
            }
        );
    }

    /**
     * @param list<string> $cmdLineArgs
     */
    public static function selectComposerLockAndInstallCmdLine(string $dbgCalledFrom, array $cmdLineArgs): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function () use ($cmdLineArgs): void {
                self::assertCount(1, $cmdLineArgs);
                $envKind = self::assertNotNull(PhpDepsEnvKind::tryToFindByName($cmdLineArgs[0]));
                self::selectComposerLockAndInstall($envKind);
            }
        );
    }

    public static function selectComposerLockAndInstall(PhpDepsEnvKind $envKind): void
    {
        $repoRootDir = ToolsUtil::getCurrentDirectory();
        self::selectComposerLock($repoRootDir);
        match ($envKind) {
            PhpDepsEnvKind::dev => self::installDev($repoRootDir),
            PhpDepsEnvKind::prod => self::installProd($repoRootDir),
        };
        self::verifyDevProdOnlyPackages($envKind, ToolsUtil::partsToPath($repoRootDir, self::mapEnvKindToVendorDirName($envKind)));
    }

    public static function selectComposerLock(string $repoRootDir): void
    {
        $generatedLockFile = self::buildToGeneratedFileFullPath($repoRootDir, self::buildGeneratedComposerLockFileNameForCurrentPhpVersion());
        $dstLockFile = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::LOCK_FILE_NAME);
        ToolsUtil::copyFile($generatedLockFile, $dstLockFile, allowOverwrite: true);
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();
    }

    public static function removeAllProdPackageFromComposerJsonAndLock(string $tempRepoDir): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        $jsonFileContents = ToolsUtil::getFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($jsonFileContents));
        $requireSection = self::assertIsArray($jsonDecoded[ComposerUtil::JSON_REQUIRE_KEY]);
        foreach ($requireSection as $fqPackageName => $_) {
            if (str_contains($fqPackageName, '/')) {
                self::composerRemoveNoScripts(PhpDepsEnvKind::prod, [$fqPackageName]);
            } else {
                self::assertSame(ComposerUtil::JSON_PHP_KEY, $fqPackageName, compact('fqPackageName'));
            }
        }
    }

    /**
     * @phpstan-param callable(string $fqPackageName): bool $shouldRemove
     */
    public static function removeDevPackageFromComposerJsonAndLock(string $tempRepoDir, callable $shouldRemove): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        $jsonFileContents = ToolsUtil::getFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($jsonFileContents));
        $requireDevSection = self::assertIsArray($jsonDecoded[ComposerUtil::JSON_REQUIRE_DEV_KEY]);
        foreach ($requireDevSection as $fqPackageName => $_) {
            if ($shouldRemove($fqPackageName)) {
                self::composerRemoveNoScripts(PhpDepsEnvKind::dev, [$fqPackageName]);
            }
        }
    }

    private static function installDev(string $repoRootDir): void
    {
        self::installInTempAndCopy(
            PhpDepsEnvKind::dev,
            $repoRootDir,
            /**
             * @phpstan-return EnvVars
             */
            preProcess: function (string $tempRepoDir): array {
                self::removeAllProdPackageFromComposerJsonAndLock($tempRepoDir);
                return [];
            }
        );
    }

    private static function installProd(string $repoRootDir): void
    {
        if (AdaptPhpDepsTo81::isCurrentPhpVersion81()) {
            AdaptPhpDepsTo81::downloadAdaptPackagesGenConfigAndInstallProd($repoRootDir);
            return;
        }

        self::installInTempAndCopy(
            PhpDepsEnvKind::prod,
            $repoRootDir,
            /**
             * @phpstan-return EnvVars
             */
            preProcess: function (string $tempRepoDir): array {
                self::removeDevPackageFromComposerJsonAndLock($tempRepoDir, shouldRemove: fn($fqPackageName) => true);
                return [];
            }
        );
    }

    /**
     * @phpstan-param callable(string $tempRepoDir): EnvVars $preProcess
     */
    public static function installInTempAndCopy(PhpDepsEnvKind $envKind, string $repoRootDir, callable $preProcess): void
    {
        ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . "_for_{$envKind->name}_",
            code: function (string $tempRepoDir) use ($envKind, $repoRootDir, $preProcess): void {
                self::copyComposerJsonLock($repoRootDir, $tempRepoDir);
                $dstToolsDir = ToolsUtil::partsToPath($tempRepoDir, 'tools');
                ToolsUtil::createDirectory($dstToolsDir);
                ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($repoRootDir, 'tools'), $dstToolsDir);
                $envVars = $preProcess($tempRepoDir);

                self::composerInstallNoScripts($envKind, $envVars);

                $dstVendorDir = ToolsUtil::partsToPath($repoRootDir, self::mapEnvKindToVendorDirName($envKind));
                ToolsUtil::ensureEmptyDirectory($dstVendorDir);
                ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::VENDOR_DIR_NAME), $dstVendorDir);
            }
        );
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function composerInstallNoScripts(PhpDepsEnvKind $envKind, array $envVars = []): void
    {
        $withDev = match ($envKind) {
            PhpDepsEnvKind::dev => true,
            PhpDepsEnvKind::prod => false,
        };
        $classmapAuthoritative = match ($envKind) {
            PhpDepsEnvKind::dev => false,
            PhpDepsEnvKind::prod => true,
        };
        ComposerUtil::execInstall($withDev, '--no-scripts', $envVars);
        ComposerUtil::execDumpAutoLoad($withDev, $classmapAuthoritative);
    }

    /**
     * @param list<string> $packagesToRemove
     */
    private static function composerRemoveNoScripts(PhpDepsEnvKind $envKind, array $packagesToRemove): void
    {
        $withDev = match ($envKind) {
            PhpDepsEnvKind::dev => true,
            PhpDepsEnvKind::prod => false,
        };
        ComposerUtil::execRemove($packagesToRemove, '--no-install --no-scripts' . ($withDev ? ' --dev' : ''));
    }

    private static function buildToGeneratedFileFullPath(string $repoRootPath, string $fileName): string
    {
        return ToolsUtil::realPath($repoRootPath . DIRECTORY_SEPARATOR . self::GENERATED_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $fileName);
    }

    private static function buildGeneratedComposerLockFileNameForCurrentPhpVersion(): string
    {
        /**
         * @see build_generated_composer_lock_file_name() finction in tool/shared.sh
         */
        return ComposerUtil::JSON_FILE_NAME_NO_EXT . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.' . ComposerUtil::LOCK_FILE_EXT;
    }

    public static function copyComposerJsonLock(string $srcDir, string $dstDir): void
    {
        foreach ([ComposerUtil::JSON_FILE_EXT, ComposerUtil::LOCK_FILE_EXT] as $composerFileExtension) {
            $composerFileName = ComposerUtil::JSON_FILE_NAME_NO_EXT . '.' . $composerFileExtension;
            ToolsUtil::copyFile(ToolsUtil::partsToPath($srcDir, $composerFileName), ToolsUtil::partsToPath($dstDir, $composerFileName));
        }
    }

    private static function mapEnvKindToVendorDirName(PhpDepsEnvKind $envKind): string
    {
        return match ($envKind) {
            PhpDepsEnvKind::dev => ComposerUtil::VENDOR_DIR_NAME,
            PhpDepsEnvKind::prod => self::VENDOR_PROD_DIR_NAME,
        };
    }

    public static function verifyDevProdOnlyPackages(PhpDepsEnvKind $envKind, string $vendorDir): void
    {
        /** @var ?array<string, list<string>> $envKindToOnlyPackages */
        static $envKindToOnlyPackages = null;
        if ($envKindToOnlyPackages === null) {
            $envKindToOnlyPackages = [
                PhpDepsEnvKind::prod->name => [
                    'open-telemetry/exporter-otlp',
                    'open-telemetry/opentelemetry-auto-curl',
                    'open-telemetry/opentelemetry-auto-laravel',
                    'open-telemetry/sdk',
                    'open-telemetry/sem-conv',
                ],
                PhpDepsEnvKind::dev->name => [
                    'dealerdirect/phpcodesniffer-composer-installer',
                    'php-parallel-lint/php-parallel-lint',
                    'phpstan/phpstan',
                    'phpstan/phpstan-phpunit',
                    'phpunit/phpunit',
                    'react/http',
                    'slevomat/coding-standard',
                    'squizlabs/php_codesniffer',
                ],
            ];
        }
        self::assertNotNull($envKindToOnlyPackages);

        foreach ($envKindToOnlyPackages as $currentEnvKind => $onlyPackages) {
            foreach ($onlyPackages as $fqPackageName) {
                $packageDir = ToolsUtil::partsToPath($vendorDir, ToolsUtil::adaptUnixDirectorySeparators($fqPackageName));
                $dbgCtx = compact('packageDir', 'vendorDir', 'fqPackageName', 'currentEnvKind');
                if ($envKind->name === $currentEnvKind) {
                    self::assertDirectoryExists($packageDir, $dbgCtx);
                } else {
                    self::assertDirectoryDoesNotExist($packageDir, $dbgCtx);
                }
            }
        }
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
