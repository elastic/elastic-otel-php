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

namespace ElasticOTelTools\Build;

use Elastic\OTel\Util\BoolUtil;
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
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_build_tools_composer_lock_files_dir in <repo root>/tools/shared.sh)
     */
    private const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_build_tools_composer_lock_files_dir in <repo root>/tools/shared.sh)
     */
    public const VENDOR_PROD_DIR_NAME = 'vendor_prod';

    public static function verifyGeneratedComposerLockFiles(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function (): void {
                $repoRootDir = ToolsUtil::getCurrentDirectory();
                foreach (PhpDepsEnvKind::cases() as $envKind) {
                    $fileName = self::buildComposerJsonFileName($envKind);
                    $repoRootJsonPath = $repoRootDir . DIRECTORY_SEPARATOR . $fileName;
                    $generatedFilesJsonPath = self::buildToGeneratedFileFullPath($repoRootDir, $fileName);
                    self::assertFilesHaveSameContent($repoRootJsonPath, $generatedFilesJsonPath);
                }
            }
        );
    }

    /**
     * @param list<string> $cmdLineArgs
     */
    public static function selectComposerLockAndInstall(string $dbgCalledFrom, array $cmdLineArgs): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function () use ($cmdLineArgs): void {
                self::assertCount(1, $cmdLineArgs);
                $envKind = self::assertNotNull(PhpDepsEnvKind::tryToFindByName($cmdLineArgs[0]));

                self::selectComposerLock($envKind);
                self::install($envKind);
            }
        );
    }

    private static function selectComposerLock(PhpDepsEnvKind $envKind): void
    {
        $repoRootDir = ToolsUtil::getCurrentDirectory();
        $generatedLockFile = self::buildToGeneratedFileFullPath($repoRootDir, self::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
        $dstLockFile = ToolsUtil::partsToPath($repoRootDir, self::mapEnvKindToGeneratedComposerFileNamePrefix($envKind) . '.' . ComposerUtil::LOCK_FILE_EXT);
        ToolsUtil::copyFile($generatedLockFile, $dstLockFile, allowOverwrite: true);
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();
    }

    private static function install(PhpDepsEnvKind $envKind): void
    {
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();

        if (AdaptPhpDepsTo81::isCurrentPhpVersion81() && ($envKind === PhpDepsEnvKind::prod)) {
            AdaptPhpDepsTo81::downloadAdaptPackagesGenConfigAndInstallProd();
        } else {
            self::installNoAdapt($envKind);
        }
    }

    private static function installNoAdapt(PhpDepsEnvKind $envKind): void
    {
        if ($envKind === PhpDepsEnvKind::dev) {
            self::composerInstallAllowDirect($envKind);
            return;
        }

        self::assertSame(PhpDepsEnvKind::prod, $envKind);
        ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . '_',
            code: function (string $tempRepoDir): void {
                $repoRootDir = ToolsUtil::getCurrentDirectory();
                self::copyComposerJsonLock(PhpDepsEnvKind::prod, $repoRootDir, $tempRepoDir);
                self::installInTempAndCopyToVendorProd($tempRepoDir, $repoRootDir);
            }
        );
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function composerInstallAllowDirect(PhpDepsEnvKind $envKind, array $envVars = []): void
    {
        $withDev = self::mapEnvKindToWithDev($envKind);
        ComposerUtil::execComposerInstallShellCommand($withDev, envVars: [ComposerUtil::ALLOW_DIRECT_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true)] + $envVars);
        ComposerUtil::execComposerDumpAutoLoad($withDev);
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function installInTempAndCopyToVendorProd(string $tempRepoDir, string $repoRootDir, array $envVars = []): void
    {
        self::renameProdComposerJsonLock($tempRepoDir);

        ToolsUtil::changeCurrentDirectoryRunCodeAndRestore(
            $tempRepoDir,
            function () use ($envVars): void {
                self::composerInstallAllowDirect(PhpDepsEnvKind::prod, $envVars);
            }
        );

        $dstVendorProdDir = ToolsUtil::partsToPath($repoRootDir, InstallPhpDeps::VENDOR_PROD_DIR_NAME);
        ToolsUtil::ensureEmptyDirectory($dstVendorProdDir);
        ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::VENDOR_DIR_NAME), $dstVendorProdDir);
    }

    public static function mapEnvKindToGeneratedComposerFileNamePrefix(PhpDepsEnvKind $envKind): string
    {
        /**
         * @see map_env_kind_to_generated_composer_file_name_prefix() finction in tool/shared.sh
         */

        $baseFileNamePrefix = 'composer';
        return match ($envKind) {
            PhpDepsEnvKind::dev => $baseFileNamePrefix,
            PhpDepsEnvKind::prod => $baseFileNamePrefix . '_' . $envKind->name,
        };
    }

    private static function buildToGeneratedFileFullPath(string $repoRootPath, string $fileName): string
    {
        return ToolsUtil::realPath($repoRootPath . DIRECTORY_SEPARATOR . self::GENERATED_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $fileName);
    }

    public static function buildComposerJsonFileName(PhpDepsEnvKind $envKind): string
    {
        /**
         * @see build_composer_json_file_name() finction in tool/shared.sh
         */

        return self::mapEnvKindToGeneratedComposerFileNamePrefix($envKind) . '.' . ComposerUtil::JSON_FILE_EXT;
    }

    private static function buildGeneratedComposerLockFileNameForCurrentPhpVersion(PhpDepsEnvKind $envKind): string
    {
        /**
         * @see build_generated_composer_lock_file_name() finction in tool/shared.sh
         */
        return self::mapEnvKindToGeneratedComposerFileNamePrefix($envKind) . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.' . ComposerUtil::LOCK_FILE_EXT;
    }

    private static function mapEnvKindToWithDev(PhpDepsEnvKind $envKind): bool
    {
        return match ($envKind) {
            PhpDepsEnvKind::dev => true,
            PhpDepsEnvKind::prod => false,
        };
    }

    public static function copyComposerJsonLock(PhpDepsEnvKind $envKind, string $srcDir, string $dstDir): void
    {
        $composerFileNameNoExt = InstallPhpDeps::mapEnvKindToGeneratedComposerFileNamePrefix($envKind);
        foreach ([ComposerUtil::JSON_FILE_EXT, ComposerUtil::LOCK_FILE_EXT] as $composerFileExtension) {
            $composerFileName = $composerFileNameNoExt . '.' . $composerFileExtension;
            ToolsUtil::copyFile(ToolsUtil::partsToPath($srcDir, $composerFileName), ToolsUtil::partsToPath($dstDir, $composerFileName));
        }
    }

    public static function renameProdComposerJsonLock(string $tempRepoDir): void
    {
        $srcFileNameNoExt = InstallPhpDeps::mapEnvKindToGeneratedComposerFileNamePrefix(PhpDepsEnvKind::prod);
        foreach ([ComposerUtil::JSON_FILE_EXT, ComposerUtil::LOCK_FILE_EXT] as $composerFileExtension) {
            $srcFileName = $srcFileNameNoExt . '.' . $composerFileExtension;
            $dstFileName = ComposerUtil::JSON_FILE_NAME_NO_EXT . '.' . $composerFileExtension;
            ToolsUtil::moveFile(ToolsUtil::partsToPath($tempRepoDir, $srcFileName), ToolsUtil::partsToPath($tempRepoDir, $dstFileName));
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
