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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTools\test\StaticCheckProd;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLog;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsUtil;

/**
 * @phpstan-import-type PackageNameToVersionMap from ComposerUtil
 * @phpstan-import-type ProdAndDevPackageNameToVersionMap from ComposerUtil
 */
final class GenerateComposerFiles
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_generated_composer_lock_files_dir_name in <repo root>/tools/shared.sh)
     */
    public const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    /**
     * Make sure the following value is in sync with the rest of locations where it's defined (see elastic_otel_php_generated_composer_files_base_file_name in <repo root>/tools/shared.sh)
     */
    public const BASE_FILE_NAME_NO_EXT = 'base';

    public static function generateForCurrentPhpVersion(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function () {
                $repoRootDir = ToolsUtil::getCurrentDirectory();
                self::verifyBaseComposerJson(ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME));

                self::generateBaseLock($repoRootDir);
                self::generateDerivedFromBase($repoRootDir);
            }
        );
    }

    public static function verifyGeneratedFiles(string $dbgCalledFrom, string $repoRootDir): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function () use ($repoRootDir): void {
                self::verifyBaseComposerJson(ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME));

                $repoRootComposerJsonPath = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME);
                $generatedFilesComposerJsonPath = self::buildFullPath($repoRootDir, self::buildJsonFileName(self::BASE_FILE_NAME_NO_EXT));
                self::assertFilesHaveSameContent($repoRootComposerJsonPath, $generatedFilesComposerJsonPath);

                foreach (PhpDepsGroup::cases() as $depsGroup) {
                    self::verifyDerivedComposerJson($depsGroup, self::buildFullPath($repoRootDir, self::buildJsonFileName($depsGroup->name)));
                }

                foreach (ToolsUtil::iterateDirectory(ToolsUtil::partsToPath($repoRootDir, self::GENERATED_FILES_DIR_NAME)) as $generatedFile) {

                    self::verifyComposerLock($depsGroup, self::buildFullPath($repoRootDir, self::buildJsonFileName($depsGroup->name)));
                }
            }
        );
    }

    private static function generateBaseLock(string $repoRootDir): void
    {
        self::assertSame($repoRootDir, ToolsUtil::getCurrentDirectory());

        $rootLockFile = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::LOCK_FILE_NAME);
        if (file_exists($rootLockFile)) {
            ToolsUtil::deleteFile($rootLockFile);
        }
        if (AdaptPhpDepsTo81::isCurrentPhpVersion81()) {
            AdaptPhpDepsTo81::generateLock($repoRootDir);
        } else {
            ComposerUtil::generateLock();
        }
        $baseLockDstFile = self::buildFullPath($repoRootDir, self::buildLockFileNameForCurrentPhpVersion(self::BASE_FILE_NAME_NO_EXT));
        ToolsUtil::copyFile($rootLockFile, $baseLockDstFile);

        self::verifyBaseComposerLock($baseLockDstFile);
    }

    private static function generateDerivedFromBase(string $repoRootDir): void
    {
        ToolsUtil::runCodeOnUniqueNameTempDir(
            tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . '_',
            code: function (string $tempRepoDir) use ($repoRootDir): void {
                $dstDir = ToolsUtil::partsToPath($tempRepoDir, self::GENERATED_FILES_DIR_NAME);
                ToolsUtil::createDirectory($dstDir);
                ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($repoRootDir, self::GENERATED_FILES_DIR_NAME), $dstDir);

                foreach (PhpDepsGroup::cases() as $depsGroup) {
                    InstallPhpDeps::selectComposerJsonAndLock($tempRepoDir, self::BASE_FILE_NAME_NO_EXT);

                    match ($depsGroup) {
                        PhpDepsGroup::dev => self::removeAllProdPackagesFromComposerJsonAndLock($tempRepoDir),
                        PhpDepsGroup::dev_for_prod_static_check => StaticCheckProd::reduceJsonAndLock($tempRepoDir),
                        PhpDepsGroup::prod => self::removeDevPackagesFromComposerJsonAndLock($tempRepoDir, shouldRemove: fn($fqPackageName) => true),
                    };

                    $derivedJsonFile = self::buildFullPath($repoRootDir, self::buildJsonFileName($depsGroup->name));
                    ToolsUtil::moveFile(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME), $derivedJsonFile);
                    $derivedLockFile = self::buildFullPath($repoRootDir, self::buildLockFileNameForCurrentPhpVersion($depsGroup->name));
                    ToolsUtil::moveFile(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::LOCK_FILE_NAME), $derivedLockFile);

                    $logLevelToShowDiff = LogLevel::debug;
                    if (ToolsLog::isLevelEnabled($logLevelToShowDiff)) {
                        $baseJsonFile = self::buildFullPath($tempRepoDir, self::buildJsonFileName(self::BASE_FILE_NAME_NO_EXT));
                        $baseLockFile = self::buildFullPath($tempRepoDir, self::buildLockFileNameForCurrentPhpVersion(self::BASE_FILE_NAME_NO_EXT));
                        self::assertNotEqual(0, ToolsUtil::execShellCommand("diff \"$baseJsonFile\" \"$derivedJsonFile\"", assertSuccessExitCode: false));
                        self::assertNotEqual(0, ToolsUtil::execShellCommand("diff \"$baseLockFile\" \"$derivedLockFile\"", assertSuccessExitCode: false));
                    }

                    self::verifyDerivedComposerJson($depsGroup, $baseJsonFile, $derivedJsonFile);
                    self::verifyDerivedComposerLock($depsGroup, $baseLockFile, $derivedLockFile);
                }
            }
        );
    }

    public static function buildJsonFileName(string $fileNamePrefix): string
    {
        /**
         * @see build_generated_composer_json_file_name() finction in tool/shared.sh
         */
        return $fileNamePrefix . '.' . ComposerUtil::JSON_FILE_EXT;
    }

    private static function buildLockFileName(string $fileNamePrefix, string $phpVersionNoDot): string
    {
        /**
         * @see build_generated_composer_lock_file_name() finction in tool/shared.sh
         */
        return $fileNamePrefix . '_' . $phpVersionNoDot . '.' . ComposerUtil::LOCK_FILE_EXT;
    }

    public static function extractPhpVersionPartFromLockFileName(string $fileName, PhpDepsGroup $depsGroup): ?string
    {
        $fileNamePrefix = $depsGroup->name . '_';
        if (!str_starts_with($fileName, $fileNamePrefix)) {
            return null;
        }
        $dotPos = self::assertNotFalse(strpos($fileName, '.'));
        self::assertGreaterThan(strlen($fileNamePrefix), $dotPos, compact('fileName', 'dotPos'));

        $phpVersionNoDot = substr($fileName, strlen($fileNamePrefix), $dotPos - strlen($fileNamePrefix));
        if (filter_var($phpVersionNoDot, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return $phpVersionNoDot;
    }

    private static function getCurrentPhpVersionNoDot(): string
    {
        return PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
    }

    public static function buildLockFileNameForCurrentPhpVersion(string $fileNamePrefix): string
    {
        return self::buildLockFileName($fileNamePrefix, phpVersionNoDot: self::getCurrentPhpVersionNoDot());
    }

    public static function buildFullPath(string $repoRootDir, string $fileName): string
    {
        return ToolsUtil::partsToPath($repoRootDir, self::GENERATED_FILES_DIR_NAME, $fileName);
    }

    public static function removeAllProdPackagesFromComposerJsonAndLock(string $tempRepoDir): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        $jsonFileContents = ToolsUtil::getFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($jsonFileContents));
        $requireSection = self::assertIsArray($jsonDecoded[ComposerUtil::REQUIRE_KEY]);
        $packagesToRemove = [];
        foreach ($requireSection as $fqPackageName => $_) {
            if (str_contains($fqPackageName, '/')) {
                $packagesToRemove[] = $fqPackageName;
            } else {
                self::assertSame(ComposerUtil::JSON_PHP_KEY, $fqPackageName, compact('fqPackageName'));
            }
        }
        ComposerUtil::removeFromComposerJsonAndLock($packagesToRemove, InstallPhpDeps::mapDepsGroupToIsDev(PhpDepsGroup::prod));
    }

    /**
     * @phpstan-param callable(string $fqPackageName): bool $shouldRemove
     */
    public static function removeDevPackagesFromComposerJsonAndLock(string $tempRepoDir, callable $shouldRemove): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        $jsonFileContents = ToolsUtil::getFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($jsonFileContents));
        $requireDevSection = self::assertIsArray($jsonDecoded[ComposerUtil::REQUIRE_DEV_KEY]);
        $packagesToRemove = [];
        foreach ($requireDevSection as $fqPackageName => $_) {
            if ($shouldRemove($fqPackageName)) {
                $packagesToRemove[] = $fqPackageName;
            }
        }
        ComposerUtil::removeFromComposerJsonAndLock($packagesToRemove, InstallPhpDeps::mapDepsGroupToIsDev(PhpDepsGroup::dev));
    }

    private const PROD_PACKAGES = [
        'open-telemetry/exporter-otlp',
        'open-telemetry/opentelemetry-auto-curl',
        'open-telemetry/opentelemetry-auto-laravel',
        'open-telemetry/sdk',
        'open-telemetry/sem-conv',
    ];

    private const ADDITIONAL_DEV_PACKAGES = [
        'phpstan/phpstan-phpunit',
        'phpunit/phpunit',
        'react/http',
    ];

    private const ALL_DEV_PACKAGES = [
        ...StaticCheckProd::DEV_FOR_PROD_STATIC_CHECK_PACKAGES,
        ...self::ADDITIONAL_DEV_PACKAGES,
    ];

    /** @var list<array{PhpDepsGroup, array{'shouldInclude': list<string>, 'shouldNotInclude': list<string>}}>  */
    private const DEPS_GROUP_TO_PACKAGES = [
        [
            PhpDepsGroup::dev,
            [
                'shouldInclude' => self::ALL_DEV_PACKAGES,
                'shouldNotInclude' => self::PROD_PACKAGES
            ]
        ],
        [
            PhpDepsGroup::dev_for_prod_static_check,
            [
                'shouldInclude' => StaticCheckProd::DEV_FOR_PROD_STATIC_CHECK_PACKAGES,
                'shouldNotInclude' => [...self::PROD_PACKAGES, ...self::ADDITIONAL_DEV_PACKAGES]
            ]
        ],
        [
            PhpDepsGroup::prod,
            [
                'shouldInclude' => self::PROD_PACKAGES,
                'shouldNotInclude' => self::ALL_DEV_PACKAGES
            ]
        ],
    ];

    /**
     * @phpstan-param array<string> $actualPackageNames
     */
    private static function verifyShouldOrNotIncludePackages(bool $shouldInclude, PhpDepsGroup $depsGroup, string $dbgActualPackageNamesSourceDesc, array $actualPackageNames): void
    {
        /** @var ?array<string, array{'shouldInclude': list<string>, 'shouldNotInclude': list<string>}> $depsGroupNameToShouldInclude */
        static $depsGroupNameToShouldOrNotInclude = null;
        if ($depsGroupNameToShouldOrNotInclude === null) {
            $depsGroupNameToShouldOrNotInclude = [];
            foreach (self::DEPS_GROUP_TO_PACKAGES as $depsGroupAndShouldIncludePair) {
                /** @var array{PhpDepsGroup, array{'shouldInclude': list<string>, 'shouldNotInclude': list<string>}} $depsGroupAndShouldIncludePair */
                $depsGroupNameToShouldOrNotInclude[$depsGroupAndShouldIncludePair[0]->name] = $depsGroupAndShouldIncludePair[1];
            }
        }
        /** @var array<string, array{'shouldInclude': list<string>, 'shouldNotInclude': list<string>}> $depsGroupNameToShouldInclude */

        $dbgCtx = [];
        ArrayUtil::prepend(compact('shouldInclude', 'depsGroup', 'dbgActualPackageNamesSourceDesc', 'actualPackageNames'), /* ref */ $dbgCtx);

        /** @var array{'shouldInclude': list<string>, 'shouldNotInclude': list<string>} $shouldOrNotInclude */
        $shouldOrNotIncludePair = self::assertArrayHasKey($depsGroup->name, $depsGroupNameToShouldOrNotInclude);
        $shouldOrNotIncludePackageNames = $shouldOrNotIncludePair[$shouldInclude ? 'shouldInclude' : 'shouldNotInclude'];
        ArrayUtil::prepend(compact('shouldOrNotIncludePackageNames'), /* ref */ $dbgCtx);
        foreach ($shouldOrNotIncludePackageNames as $shouldOrNotPackageName) {
            ArrayUtil::prepend(compact('shouldOrNotPackageName'), /* ref */ $dbgCtx);
            $isInArray = in_array($shouldOrNotPackageName, $actualPackageNames);
            ArrayUtil::prepend(compact('isInArray'), /* ref */ $dbgCtx);
            self::assert($shouldInclude ? $isInArray : !$isInArray, ' ; ' . json_encode($dbgCtx));
        }
    }

    private const BASE_DEPS_GROUP_PACKAGES_SECTION = [
        [PhpDepsGroup::prod, ComposerPackagesSection::prod],
        [PhpDepsGroup::dev, ComposerPackagesSection::dev],
    ];

    /**
     * @param ProdAndDevPackageNameToVersionMap $prodAndDevPackageNameToVersionMap
     */
    private static function verifyBaseComposerFile(string $dbgFilePath, array $prodAndDevPackageNameToVersionMap): void
    {
        foreach (self::BASE_DEPS_GROUP_PACKAGES_SECTION as [$depsGroup, $packagesSection]) {
            $packagesNamesToVersions = self::assertArrayHasKey($packagesSection->name, $prodAndDevPackageNameToVersionMap);
            self::verifyShouldOrNotIncludePackages(/* shouldInclude */ true, $depsGroup, $dbgFilePath, array_keys($packagesNamesToVersions));
        }
    }

    private static function verifyBaseComposerJson(string $filePath): void
    {
        self::verifyBaseComposerFile($filePath, ComposerUtil::readPackagesVersionsFromJson($filePath));
    }

    private static function verifyDerivedIsSubsetOfBase(string $repoRootDir): void
    {
        foreach (PhpDepsGroup::cases() as $depsGroup) {
            self::verifyDerivedIsSubsetOfBaseForDepsGroup($repoRootDir, $depsGroup);
        }
    }

    private static function verifyDerivedIsSubsetOfBaseForDepsGroup(string $repoRootDir, PhpDepsGroup $depsGroup): void
    {
        $foundDerivedLockFiles = [];
        foreach (ToolsUtil::iterateDirectory(ToolsUtil::partsToPath($repoRootDir, self::GENERATED_FILES_DIR_NAME)) as $generatedFileInfo) {
            if ($generatedFileInfo->getExtension() === ComposerUtil::LOCK_FILE_EXT && (self::extractPhpVersionPartFromLockFileName($generatedFileInfo->getBasename(), $depsGroup) !== null)) {
                $foundDerivedLockFiles[] = $generatedFileInfo->getRealPath();
            }
        }
        sort(/* ref */ $foundDerivedLockFiles);

        foreach ($foundDerivedLockFiles as $derivedLockFileFullPath) {
            self::verifyDerivedFileIsSubsetOfBase($depsGroup, $derivedLockFileFullPath);
        }
    }

    private static function verifyDerivedFileIsSubsetOfBase(PhpDepsGroup $depsGroup, string $derivedLockFileFullPath): void
    {
        $dbgCtx = compact('derivedLockFileFullPath');

        $derivedLockFileName = basename($derivedLockFileFullPath);
        $dbgCtx += compact('derivedLockFileName');
        $phpVersionNoDot = self::assertNotNull(self::extractPhpVersionPartFromLockFileName($derivedLockFileName, $depsGroup));
        $baseLockFileName = self::buildLockFileName(self::BASE_FILE_NAME_NO_EXT, $phpVersionNoDot);
        $baseLockFileFullPath = ToolsUtil::partsToPath(dirname($derivedLockFileFullPath), $baseLockFileName);
        $dbgCtx += compact('baseLockFileFullPath');
        self::assertFileExists($baseLockFileFullPath, array_reverse($dbgCtx));

        $basePackagesVersions = ComposerUtil::readPackagesVersionsFromLock($baseLockFileFullPath);
        $dbgCtx += compact('basePackagesVersions');
        $derivedPackagesVersions = ComposerUtil::readPackagesVersionsFromLock($derivedLockFileFullPath);
        $dbgCtx += compact('derivedPackagesVersions');

        $dbgCtxPackageIt = [];
        $dbgCtx['dbgCtxPackageIt'] =& $dbgCtxPackageIt;
        foreach ($derivedPackagesVersions as $packageName => $derivedPackageVersion) {
            $dbgCtxPackageIt = [];
            $dbgCtxPackageIt = compact('packageName', 'derivedPackageVersion');
            self::assertNotFalse(ArrayUtil::getValueIfKeyExists($packageName, $basePackagesVersions, /* out */ $basePackageVersion));
            $dbgCtxPackageIt += compact('derivedPackageVersion');
            if ($basePackageVersion !== $derivedPackageVersion) {
                self::assertFail(
                    'Encountered a package with different versions in base and derived lock files'
                    . "; package name: $packageName, version in base lock: $basePackageVersion, version in derived lock: $derivedPackageVersion"
                    . ", base lock file: $baseLockFileName, derived lock file: $derivedLockFileName"
                    . ' ; ' . ToolsUtil::encodeJson($dbgCtx)
                );
            }
        }
        $dbgCtxPackageIt = [];
    }

    private static function verifyBaseComposerLock(string $filePath): void
    {
        self::verifyBaseComposerFile($filePath, ComposerUtil::readPackagesVersionsFromLock($filePath));
    }

    /**
     * @param ProdAndDevPackageNameToVersionMap $prodAndDevPackageNameToVersionMap
     */
    private static function verifyDerivedComposerFile(
        PhpDepsGroup $depsGroup,
        string $dbgBaseFilePath,
        array $baseProdAndDevPackageNameToVersionMap,
        string $dbgDerivedFilePath,
        array $derivedProdAndDevPackageNameToVersionMap,
    ): void {
        $dbgCtx = [];
        ArrayUtil::prepend(compact('depsGroup', 'dbgFilePath'), /* ref */ $dbgCtx);
        $isDevToPackagesSectionId = fn (bool $isDev) => $isDev ? ComposerPackagesSection::dev : ComposerPackagesSection::prod;

        $packagesSectionIdThatShouldBeEmpty = $isDevToPackagesSectionId(!InstallPhpDeps::mapDepsGroupToIsDev($depsGroup))->name;
        ArrayUtil::prepend(compact('packagesSectionIdThatShouldBeEmpty'), /* ref */ $dbgCtx);
        $sectionThatShouldBeEmpty  = self::assertArrayHasKey($packagesSectionIdThatShouldBeEmpty, $prodAndDevPackageNameToVersionMap);
        ArrayUtil::prepend(compact('sectionThatShouldBeEmpty'), /* ref */ $dbgCtx);
        self::assertArrayIsEmpty($sectionThatShouldBeEmpty, $dbgCtx);

        $packagesSectionIdThatShouldNotBeEmpty = $isDevToPackagesSectionId(InstallPhpDeps::mapDepsGroupToIsDev($depsGroup))->name;
        $packageNames = array_keys(self::assertIsArray(self::assertArrayHasKey($packagesSectionIdThatShouldNotBeEmpty, $prodAndDevPackageNameToVersionMap)));
        ArrayUtil::prepend(compact('packageNames'), /* ref */ $dbgCtx);

        foreach ([true, false] as $shouldInclude) {
            self::verifyShouldOrNotIncludePackages($shouldInclude, $depsGroup, $dbgFilePath, $packageNames);
        }
    }

    private static function verifyDerivedComposerJson(PhpDepsGroup $depsGroup, string $baseJsonFile, string $derivedJsonFile): void
    {
        self::verifyDerivedComposerFile(
            $depsGroup,
            $baseJsonFile,
            ComposerUtil::readPackagesVersionsFromJson($baseJsonFile),
            $derivedJsonFile,
            ComposerUtil::readPackagesVersionsFromJson($derivedJsonFile)
        );
    }

    private static function verifyDerivedComposerLock(PhpDepsGroup $depsGroup, string $filePath): void
    {
        self::verifyDerivedComposerFile($depsGroup, $filePath, ComposerUtil::readPackagesVersionsFromLock($filePath));

    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
