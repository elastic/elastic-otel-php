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

namespace ElasticOTelTools\Test;

use ElasticOTelTools\Build\ComposerUtil;
use ElasticOTelTools\Build\InstallPhpDeps;
use ElasticOTelTools\Build\PhpDepsEnvKind;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsUtil;

final class StaticCheckProd
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    private const COMPOSER_JSON_REQUIRE_DEV_KEY = 'require-dev';

    public static function check(string $dbgCalledFrom): void
    {
        ToolsUtil::runCmdLineImpl(
            $dbgCalledFrom,
            function (): void {
                ToolsUtil::runCodeOnUniqueNameTempDir(
                    tempDirNamePrefix: ToolsUtil::fqClassNameToShort(__CLASS__) . '_' . __FUNCTION__ . '_',
                    code: function (string $tempRepoDir): void {
                        $repoRootDir = ToolsUtil::getCurrentDirectory();
                        InstallPhpDeps::copyComposerJsonLock(PhpDepsEnvKind::dev, $repoRootDir, $tempRepoDir);
                        ToolsUtil::changeCurrentDirectoryRunCodeAndRestore(
                            $tempRepoDir,
                            function () use ($tempRepoDir): void {
                                self::reduceDevJson($tempRepoDir);
                                ToolsUtil::listFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
                                ToolsUtil::listDirectoryContents($tempRepoDir);
                                InstallPhpDeps::composerInstallAllowDirect(PhpDepsEnvKind::dev);
                                ToolsUtil::listDirectoryContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::VENDOR_DIR_NAME));
                            },
                        );
                    }
                );

                //# To run static check on prod (without dev only dependencies)
                //local PhpStan_neon_bootstrapFiles_value_line_original="- ./tests/bootstrap.php"
                //local PhpStan_neon_bootstrapFiles_value_line_for_prod_static_check="- ./tests/bootstrapProdStaticCheck.php"
                //local PhpStan_neon_bootstrapFiles_value_line_original_escaped="${PhpStan_neon_bootstrapFiles_value_line_original//\//\\\/}"
                //local PhpStan_neon_bootstrapFiles_value_line_for_prod_static_check_escaped="${PhpStan_neon_bootstrapFiles_value_line_for_prod_static_check//\//\\\/}"
                //local replace_PhpStan_neon_bootstrapFiles_for_prod_static_check=\
                //                  "sed -i 's/${PhpStan_neon_bootstrapFiles_value_line_original_escaped}/${PhpStan_neon_bootstrapFiles_value_line_for_prod_static_check_escaped}/g'"
                //&& mkdir -p /tmp/repo \
                //&& cp -r /repo_root/* /tmp/repo/ \
                //&& rm -rf /tmp/repo/composer.json /tmp/repo/composer.lock /tmp/repo/vendor/ /tmp/repo/prod/php/vendor_* \
                //&& mv /tmp/repo/tests /tmp/repo/tests_original \
                //&& mkdir /tmp/repo/tests \
                //&& mv /tmp/repo/tests_original/elastic_otel_extension_stubs /tmp/repo/tests/elastic_otel_extension_stubs \
                //&& mv /tmp/repo/tests_original/bootstrapProdStaticCheck.php /tmp/repo/tests/bootstrapProdStaticCheck.php \
                //&& rm -rf /tmp/repo/tests_original/ \
                //&& ${replace_PhpStan_neon_bootstrapFiles_for_prod_static_check} /tmp/repo/phpstan.dist.neon \
                //&& cd /tmp/repo/ \
                //&& php ./tools/build/select_composer_json_lock_and_install_PHP_deps.php prod_static_check \
                //&& composer run-script -- static_check \
                //                $repoRootDir = ToolsUtil::getCurrentDirectory();
                // TODO: Sergey Kleyman: Implement: tools/test/static_check_prod.php

                self::assertFail('TODO: Sergey Kleyman: Implement: ' . __METHOD__);
            }
        );
    }

    /**
     * @return list<string>
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function devPackagesNotUsedForStaticCheck(string $tempRepoDir): array
    {
        /** @var list<string> $packagesPrefixesToKeep */
        static $packagesPrefixesToKeep = [
            'php-parallel-lint/',
            'phpstan/'
        ];
        /** @var list<string> $packagesToKeep */
        static $packagesToKeep = [
            'slevomat/coding-standard',
            'squizlabs/php_codesniffer'
        ];

        $shouldKeepPackage = static function (string $fqPackageName) use ($packagesPrefixesToKeep, $packagesToKeep): bool {
            foreach ($packagesPrefixesToKeep as $packagesPrefixToKeep) {
                if (str_starts_with($fqPackageName, $packagesPrefixToKeep)) {
                    return true;
                }
            }
            return in_array($fqPackageName, $packagesToKeep);
        };

        $jsonFileContents = ToolsUtil::getFileContents(ToolsUtil::partsToPath($tempRepoDir, ComposerUtil::JSON_FILE_NAME));
        $jsonDecoded = self::assertIsArray(ToolsUtil::decodeJson($jsonFileContents, asAssocArray: true));
        $requireDevSection = self::assertIsArray($jsonDecoded[self::COMPOSER_JSON_REQUIRE_DEV_KEY]);
        $result = [];
        foreach ($requireDevSection as $fqPackageName => $_) {
            if (!$shouldKeepPackage($fqPackageName)) {
                $result[] = $fqPackageName;
            }
        }
        return $result;
    }

    private static function reduceDevJson(string $tempRepoDir): void
    {
        // We should not manipulate composer.json directly because
        // we would like for composer.lock to updated as well
        ComposerUtil::execComposerRemove(self::devPackagesNotUsedForStaticCheck($tempRepoDir), '--no-scripts --no-update --dev');
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
