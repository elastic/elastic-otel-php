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
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_vendor_prod_dir_name in <repo root>/tools/shared.sh)
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
                    self::verifyPackagesVersionsInDevAndProdLockMatch($repoRootDir);
                }
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
        self::selectComposerLock($envKind);
        self::install($envKind);
    }

    public static function selectComposerLock(PhpDepsEnvKind $envKind): void
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
        $repoRootDir = ToolsUtil::getCurrentDirectory();
        ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . '_',
            code: function (string $tempRepoDir) use ($repoRootDir): void {
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
        $withDev = match ($envKind) {
            PhpDepsEnvKind::dev => true,
            PhpDepsEnvKind::prod => false,
        };
        $classmapAuthoritative = match ($envKind) {
            PhpDepsEnvKind::dev => false,
            PhpDepsEnvKind::prod => true,
        };
        ComposerUtil::execInstall($withDev, envVars: [ComposerUtil::ALLOW_DIRECT_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true)] + $envVars);
        ComposerUtil::execDumpAutoLoad($withDev, $classmapAuthoritative);
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function installInTempAndCopyToVendorProd(string $tempRepoDir, string $repoRootDir, array $envVars = []): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        self::renameProdComposerJsonLock($tempRepoDir);

        self::composerInstallAllowDirect(PhpDepsEnvKind::prod, $envVars);

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

    private const PACKAGES_KEY = 'packages';
    private const PACKAGES_DEV_KEY = 'packages-dev';
    private const NAME_KEY = 'name';
    private const VERSION_KEY = 'version';

    /**
     * @return non-empty-array<string, string>
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function readPackagesVersions(string $lockFilePath): array
    {
        $dbgCtx = compact('lockFilePath');
        $dbgCtxPackagesSectionIt = [];
        $dbgCtx['dbgCtxPackagesSectionIt'] =& $dbgCtxPackagesSectionIt;
        $dbgCtxPackagePropsIt = [];
        $dbgCtx['dbgCtxPackagePropsIt'] =& $dbgCtxPackagePropsIt;

        $decodedLock = self::assertIsArray(ToolsUtil::decodeJson(ToolsUtil::getFileContents($lockFilePath)));
        $result = [];

        foreach ([self::PACKAGES_KEY, self::PACKAGES_DEV_KEY] as $packagesSectionKey) {
            $dbgCtxPackagesSectionIt = compact('packagesSectionKey');
            if (!ArrayUtil::getValueIfKeyExists($packagesSectionKey, $decodedLock, /* out */ $packagesSection)) {
                continue;
            }
            self::assertIsArray($packagesSection);
            foreach ($packagesSection as $packageProps) {
                $dbgCtxPackagePropsIt = compact('packageProps');
                self::assertIsArray($packageProps);
                $packageName = self::assertIsString(self::assertArrayHasKey(self::NAME_KEY, $packageProps));
                $packageVersion = self::assertIsString(self::assertArrayHasKey(self::VERSION_KEY, $packageProps));
                if (ArrayUtil::getValueIfKeyExists($packageName, $result, /* out */ $alreadyPresentPackageVersion)) {
                    self::assertSame($alreadyPresentPackageVersion, $packageVersion);
                } else {
                    $result[$packageName] = $packageVersion;
                }
            }
            $dbgCtxPackagePropsIt = [];
        }
        $dbgCtxPackagesSectionIt = [];

        /** @var array<string, string> $result */
        return self::assertArrayNotEmpty($result);
    }

    private static function verifyPackagesVersionsInDevAndProdLockMatch(string $repoRootDir): void
    {
        $prodLockFilePrefix = self::mapEnvKindToGeneratedComposerFileNamePrefix(PhpDepsEnvKind::prod);
        $foundProdLockFiles = [];
        foreach (ToolsUtil::iterateDirectory(ToolsUtil::partsToPath($repoRootDir, self::GENERATED_FILES_DIR_NAME)) as $generatedFileInfo) {
            if (str_starts_with($generatedFileInfo->getBasename(), $prodLockFilePrefix) && $generatedFileInfo->getExtension() === ComposerUtil::LOCK_FILE_EXT) {
                $foundProdLockFiles[] = $generatedFileInfo->getRealPath();
            }
        }
        sort(/* ref */ $foundProdLockFiles);
        foreach ($foundProdLockFiles as $prodLockFileFullPath) {
            $dbgCtx = compact('prodLockFileFullPath');

            $prodLockFileName = basename($prodLockFileFullPath);
            $dbgCtx += compact('prodLockFileName');
            $devLockFileName = self::mapEnvKindToGeneratedComposerFileNamePrefix(PhpDepsEnvKind::dev) . substr($prodLockFileName, strlen($prodLockFilePrefix));
            $devLockFileFullPath = ToolsUtil::partsToPath(dirname($prodLockFileFullPath), $devLockFileName);
            $dbgCtx += compact('devLockFileFullPath');
            self::assertFileExists($devLockFileFullPath, array_reverse($dbgCtx));

            $devPackagesVersions = self::readPackagesVersions($devLockFileFullPath);
            $dbgCtx += compact('devPackagesVersions');
            $prodPackagesVersions = self::readPackagesVersions($prodLockFileFullPath);
            $dbgCtx += compact('prodPackagesVersions');
            $dbgCtxPackageIt = [];
            $dbgCtx['dbgCtxPackageIt'] =& $dbgCtxPackageIt;
            foreach ($devPackagesVersions as $packageName => $devPackageVersion) {
                $dbgCtxPackageIt = [];
                $dbgCtxPackageIt = compact('packageName', 'devPackageVersion');
                if (ArrayUtil::getValueIfKeyExists($packageName, $prodPackagesVersions, /* out */ $prodPackageVersion)) {
                    $dbgCtxPackageIt += compact('prodPackageVersion');
                    if ($devPackageVersion === $prodPackageVersion) {
                        continue;
                    }
                    self::assertFail(
                        'Encountered a package with different versions in dev and prod lock files'
                        . "; package name: $packageName, version in dev lock: $devPackageVersion, version in prod lock: $prodPackageVersion"
                        . ", dev lock file: $devLockFileName, prod lock file: $prodLockFileName"
                        . ' ; ' . ToolsUtil::encodeJson($dbgCtx)
                    );
                }
            }
            $dbgCtxPackageIt = [];
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
