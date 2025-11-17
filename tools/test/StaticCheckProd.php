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

namespace ElasticOTelTools\test;

use ElasticOTelTools\build\ComposerUtil;
use ElasticOTelTools\build\InstallPhpDeps;
use ElasticOTelTools\build\PhpDepsEnvKind;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsUtil;

final class StaticCheckProd
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    public static function check(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function (): void {
                ComposerUtil::verifyThatComposerJsonAndLockAreInSync();
                $repoRootDir = ToolsUtil::getCurrentDirectory();
                ToolsUtil::runCodeOnUniqueNameTempDir(
                    tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_',
                    code: function (string $tempRepoDir) use ($repoRootDir): void {
                        self::checkImpl($repoRootDir, $tempRepoDir);
                    }
                );
            }
        );
    }

    private static function checkImpl(string $repoRootDir, string $tempRepoDir): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        self::copyRepoExcludeGenerated($repoRootDir, $tempRepoDir);

        // in <repo root>/tests leave only elastic_otel_extension_stubs
        ToolsUtil::deleteDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, 'tests'));
        $subDirToKeep = 'tests/elastic_otel_extension_stubs';
        $dstSubDir = ToolsUtil::partsToPath($tempRepoDir, ToolsUtil::adaptUnixDirectorySeparators($subDirToKeep));
        ToolsUtil::createDirectory($dstSubDir);
        ToolsUtil::copyDirectoryContents(ToolsUtil::partsToPath($repoRootDir, ToolsUtil::adaptUnixDirectorySeparators($subDirToKeep)), $dstSubDir);

        // Install $tempRepoDir/vendor
        InstallPhpDeps::selectComposerLock($tempRepoDir);
        self::reduceJsonAndLock($tempRepoDir);
        ToolsUtil::listFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        InstallPhpDeps::composerInstallNoScripts(PhpDepsEnvKind::dev);
        ToolsUtil::listDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::VENDOR_DIR_NAME));

        // Restore original composer.json
        ToolsUtil::copyFile(ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME), ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        // Install $tempRepoDir/vendor_prod
        InstallPhpDeps::selectComposerLockAndInstall(PhpDepsEnvKind::prod);

        self::adaptPhpStanConfig($tempRepoDir);

        ComposerUtil::execCommand('composer run-script -- static_check');
    }

    private static function copyRepoExcludeGenerated(string $srcRepoDir, string $dstRepoDir): void
    {
        self::assert(!ToolsUtil::isCurrentOsWindows(), __METHOD__ . ' is not implemented (yet?) on Windows');
        ToolsUtil::execShellCommand("$srcRepoDir/tools/copy_repo_exclude_generated.sh \"$srcRepoDir\" \"$dstRepoDir\"");
    }

    private static function reduceJsonAndLock(string $tempRepoDir): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        // We should not manipulate composer.json directly because
        // we would like for composer.lock to updated as well
        InstallPhpDeps::removeAllProdPackageFromComposerJsonAndLock($tempRepoDir);

        /** @var list<string> $devPackagesPrefixesToKeep */
        static $devPackagesPrefixesToKeep = [
            'php-parallel-lint/',
            'phpstan/'
        ];
        /** @var list<string> $devPackagesToKeep */
        static $devPackagesToKeep = [
            'dealerdirect/phpcodesniffer-composer-installer',
            'slevomat/coding-standard',
            'squizlabs/php_codesniffer'
        ];

        $shouldRemoveDevPackage = static function (string $fqPackageName) use ($devPackagesPrefixesToKeep, $devPackagesToKeep): bool {
            foreach ($devPackagesPrefixesToKeep as $packagesPrefixToKeep) {
                if (str_starts_with($fqPackageName, $packagesPrefixToKeep)) {
                    return false;
                }
            }
            return !in_array($fqPackageName, $devPackagesToKeep);
        };

        InstallPhpDeps::removeDevPackageFromComposerJsonAndLock($tempRepoDir, $shouldRemoveDevPackage);
    }

    private static function adaptPhpStanConfig(string $tempRepoDir): void
    {
        $phpStanConfigFilePath = ToolsUtil::partsToPath($tempRepoDir, 'phpstan.dist.neon');
        ToolsUtil::putFileContents($phpStanConfigFilePath, self::adaptPhpStanConfigContent(ToolsUtil::getFileContents($phpStanConfigFilePath)));
    }

    public static function adaptPhpStanConfigContent(string $content): string
    {
        $newContent = str_replace('- ./tools/test/PHPStan_bootstrapFiles.php', '- ./tools/test/PHPStan_bootstrapFiles_static_check_prod.php', $content, /* out */ $countReplaced);
        self::assertSame(1, $countReplaced);
        return $newContent;
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
