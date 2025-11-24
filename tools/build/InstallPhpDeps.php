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
use Elastic\OTel\Util\BoolUtil;
use ElasticOTelTools\test\StaticCheckProd;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsUtil;

/**
 * @phpstan-import-type EnvVars from ToolsUtil
 * @phpstan-import-type PackageNameToVersionMap from ComposerUtil
 */
final class InstallPhpDeps
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_vendor_prod_dir_name in <repo root>/tools/shared.sh)
     */
    public const VENDOR_PROD_DIR_NAME = 'vendor_prod';

    /**
     * @param list<string> $cmdLineArgs
     */
    public static function selectComposerLockAndInstallCmdLine(string $dbgCalledFrom, array $cmdLineArgs): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function () use ($cmdLineArgs): void {
                self::assertCount(1, $cmdLineArgs);
                $depsGroup = self::assertNotNull(PhpDepsGroup::tryToFindByName($cmdLineArgs[0]));
                self::selectComposerLockAndInstall($depsGroup);
            }
        );
    }

    public static function selectComposerLockAndInstall(PhpDepsGroup $depsGroup): void
    {
        $repoRootDir = ToolsUtil::getCurrentDirectory();
        self::selectComposerLock($repoRootDir, GenerateComposerFiles::BASE_FILE_NAME_NO_EXT, allowOverwrite: true);
        match ($depsGroup) {
            PhpDepsGroup::dev, PhpDepsGroup::dev_for_prod_static_check => self::installInTempAndCopy($depsGroup, $repoRootDir),
            PhpDepsGroup::prod => self::installProd($repoRootDir),
        };
        self::verifyDevProdOnlyPackages($depsGroup, ToolsUtil::partsToPath($repoRootDir, self::mapDepsGroupToVendorDirName($depsGroup)));
    }

    public static function selectComposerLock(string $repoRootDir, string $fileNamePrefix, bool $allowOverwrite = false): void
    {
        $generatedLockFile = GenerateComposerFiles::buildFullPath($repoRootDir, GenerateComposerFiles::buildLockFileNameForCurrentPhpVersion($fileNamePrefix));
        $dstLockFile = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::LOCK_FILE_NAME);
        ToolsUtil::copyFile($generatedLockFile, $dstLockFile, $allowOverwrite);
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();
    }

    private static function installProd(string $repoRootDir): void
    {
        if (AdaptPhpDepsTo81::isCurrentPhpVersion81()) {
            AdaptPhpDepsTo81::downloadAdaptPackagesGenConfigAndInstallProd($repoRootDir);
        } else {
            self::installInTempAndCopy(PhpDepsGroup::prod, $repoRootDir);
        }
    }

    public static function selectComposerJsonAndLock(string $repoRootDir, string $fileNamePrefix): void
    {
        $derivedJsonFile = GenerateComposerFiles::buildFullPath($repoRootDir, GenerateComposerFiles::buildJsonFileName($fileNamePrefix));
        $dstJsonFile = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME);
        ToolsUtil::copyFile($derivedJsonFile, $dstJsonFile);
        self::selectComposerLock($repoRootDir, $fileNamePrefix);
    }

    /**
     * @phpstan-param ?callable(string $tempRepoDir): EnvVars $preProcess
     */
    public static function installInTempAndCopy(PhpDepsGroup $depsGroup, string $repoRootDir, ?callable $preProcess = null): void
    {
        ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . "_for_{$depsGroup->name}_",
            code: function (string $tempRepoDir) use ($depsGroup, $repoRootDir, $preProcess): void {
                foreach ([GenerateComposerFiles::GENERATED_FILES_DIR_NAME, 'prod/php', 'tools'] as $dirToCopyRelPath) {
                    $dirToCopyRelPathAdapted = ToolsUtil::adaptUnixDirectorySeparators($dirToCopyRelPath);
                    $dstDir = ToolsUtil::partsToPath($tempRepoDir, $dirToCopyRelPathAdapted);
                    ToolsUtil::createDirectory($dstDir);
                    ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($repoRootDir, $dirToCopyRelPathAdapted), $dstDir);
                }

                self::selectComposerJsonAndLock($tempRepoDir, $depsGroup->name);

                $envVars = ($preProcess === null) ? [] : $preProcess($tempRepoDir);

                self::composerInstall($depsGroup, $envVars);

                $dstVendorDir = ToolsUtil::partsToPath($repoRootDir, self::mapDepsGroupToVendorDirName($depsGroup));
                ToolsUtil::ensureEmptyDirectory($dstVendorDir);
                ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::VENDOR_DIR_NAME), $dstVendorDir);
            }
        );
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function composerInstall(PhpDepsGroup $depsGroup, array $envVars = []): void
    {
        $withDev = self::mapDepsGroupToIsDev($depsGroup);
        $classmapAuthoritative = match ($depsGroup) {
            PhpDepsGroup::dev => false,
            PhpDepsGroup::prod, PhpDepsGroup::dev_for_prod_static_check => true,
        };
        ComposerUtil::execInstall($withDev, envVars: $envVars);
        ComposerUtil::execDumpAutoLoad($withDev, $classmapAuthoritative);
    }

    private static function mapDepsGroupToVendorDirName(PhpDepsGroup $depsGroup): string
    {
        return match ($depsGroup) {
            PhpDepsGroup::dev, PhpDepsGroup::dev_for_prod_static_check => ComposerUtil::VENDOR_DIR_NAME,
            PhpDepsGroup::prod => self::VENDOR_PROD_DIR_NAME,
        };
    }

    public static function mapDepsGroupToIsDev(PhpDepsGroup $depsGroup): bool
    {
        return match ($depsGroup) {
            PhpDepsGroup::dev, PhpDepsGroup::dev_for_prod_static_check => true,
            PhpDepsGroup::prod => false,
        };
    }

    public static function verifyVendorDir(PhpDepsGroup $depsGroup, string $vendorDir): void
    {
        foreach ($depsGroupToOnlyPackages as $currentDepsGroup => $onlyPackages) {
            foreach ($onlyPackages as $fqPackageName) {
                $packageDir = ToolsUtil::partsToPath($vendorDir, ToolsUtil::adaptUnixDirectorySeparators($fqPackageName));
                $dbgCtx = compact('packageDir', 'vendorDir', 'fqPackageName', 'currentDepsGroup');
                if ($depsGroup->name === $currentDepsGroup) {
                    self::assertDirectoryExists($packageDir, $dbgCtx);
                } else {
                    self::assertDirectoryDoesNotExist($packageDir, $dbgCtx);
                }
            }
        }
    }

    /**
     * @param PackageNameToVersionMap $packageNameToVersionMap
     */
    public static function verifyDevProdOnlyPackages(PhpDepsGroup $depsGroup, array $packageNameToVersionMap): void
    {
        /** @var ?array<string, list<string>> $depsGroupToOnlyPackages */
        static $depsGroupToOnlyPackages = null;
        if ($depsGroupToOnlyPackages === null) {
            $depsGroupToOnlyPackages = [
                PhpDepsGroup::prod->name => [
                ],
                PhpDepsGroup::dev->name => [
                ],
            ];
        }
        self::assertNotNull($depsGroupToOnlyPackages);

        foreach ($depsGroupToOnlyPackages as $currentDepsGroup => $onlyPackages) {
            foreach ($onlyPackages as $fqPackageName) {
                $packageDir = ToolsUtil::partsToPath($vendorDir, ToolsUtil::adaptUnixDirectorySeparators($fqPackageName));
                $dbgCtx = compact('packageDir', 'vendorDir', 'fqPackageName', 'currentDepsGroup');
                if ($depsGroup->name === $currentDepsGroup) {
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
