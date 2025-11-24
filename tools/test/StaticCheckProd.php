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
use ElasticOTelTools\build\GenerateComposerFiles;
use ElasticOTelTools\build\InstallPhpDeps;
use ElasticOTelTools\build\PhpDepsGroup;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsUtil;

final class StaticCheckProd
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    public static function checkOnRepoTempCopy(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function (): void {
                self::checkOnRepoTempCopyImpl();
            }
        );
    }

    private static function checkOnRepoTempCopyImpl(): void
    {
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();

        $tempRepoDir = ToolsUtil::getCurrentDirectory();

        // in <repo root>/tests leave only elastic_otel_extension_stubs
        foreach (ToolsUtil::iterateDirectory(ToolsUtil::partsToPath($tempRepoDir, 'tests')) as $itemInTestsDir) {
            if ($itemInTestsDir->getBasename() === 'elastic_otel_extension_stubs') {
                continue;
            }
            if ($itemInTestsDir->isDir()) {
                ToolsUtil::deleteDirectory($itemInTestsDir->getRealPath());
            } else {
                ToolsUtil::deleteFile($itemInTestsDir->getRealPath());
            }
        }

        // Install $tempRepoDir/vendor
        InstallPhpDeps::selectComposerJsonAndLock($tempRepoDir, PhpDepsGroup::dev_for_prod_static_check->name);
        InstallPhpDeps::composerInstall(PhpDepsGroup::dev_for_prod_static_check);

        // Delete composer.json/composer.lock which will be replaced by prod variants next
        foreach ([ComposerUtil::JSON_FILE_NAME, ComposerUtil::LOCK_FILE_NAME] as $fileName) {
            ToolsUtil::deleteFile(ToolsUtil::partsToPath($tempRepoDir, $fileName));
        }

        // Install $tempRepoDir/vendor_prod
        InstallPhpDeps::selectComposerJsonAndLock($tempRepoDir, PhpDepsGroup::prod->name);
        InstallPhpDeps::selectComposerLockAndInstall(PhpDepsGroup::prod);

        self::adaptPhpStanConfig($tempRepoDir);

        ComposerUtil::execRunScript('static_check');
    }

    public const DEV_FOR_PROD_STATIC_CHECK_PACKAGES = [
        'dealerdirect/phpcodesniffer-composer-installer',
        'php-parallel-lint/php-console-highlighter',
        'php-parallel-lint/php-parallel-lint',
        'phpstan/phpstan',
        'slevomat/coding-standard',
        'squizlabs/php_codesniffer'
    ];

    public static function reduceJsonAndLock(string $tempRepoDir): void
    {
        self::assertSame($tempRepoDir, ToolsUtil::getCurrentDirectory());

        // We should not manipulate composer.json directly because
        // we would like for composer.lock to updated as well
        GenerateComposerFiles::removeAllProdPackagesFromComposerJsonAndLock($tempRepoDir);

        /** @var list<string> $devPackagesPrefixesToKeep */
        static $devPackagesPrefixesToKeep = [
        ];
        /** @var list<string> $devPackagesToKeep */
        static $devPackagesToKeep = [
        ];

        $shouldRemoveDevPackage = static function (string $fqPackageName) use ($devPackagesPrefixesToKeep, $devPackagesToKeep): bool {
            foreach ($devPackagesPrefixesToKeep as $packagesPrefixToKeep) {
                if (str_starts_with($fqPackageName, $packagesPrefixToKeep)) {
                    return false;
                }
            }
            return !in_array($fqPackageName, $devPackagesToKeep);
        };

        GenerateComposerFiles::removeDevPackagesFromComposerJsonAndLock($tempRepoDir, $shouldRemoveDevPackage);
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
