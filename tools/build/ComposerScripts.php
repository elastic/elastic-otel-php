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

use Composer\Script\Event;
use Elastic\OTel\AutoloaderElasticOTelClasses;
use RuntimeException;
use Throwable;

/**
 * This class is used in scripts section of composer.json
 *
 * @noinspection PhpUnused
 */
final class ComposerScripts
{
    private const FAILURE_EXIT_CODE = 1;

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function adaptComposerJsonDownloadAndAdaptPackages(Event $event): void
    {
        self::runCmdLineImpl(fn() => AdaptPackagesToPhp81::adaptComposerJsonDownloadAndAdaptPackages(self::getCommandLineArgs($event)));
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function installDevSelectLock(): void
    {
        self::runCmdLineImpl(
            function (): void {
                self::verifyGeneratedComposerLockFiles();

                self::selectGeneratedDevLock();

                if (AdaptPackagesToPhp81::isCurrentPhpVersion81()) {
                    AdaptPackagesToPhp81::installSelectJsonLock(NoDevEnvUtil::DEV_ENV_KIND);
                } else {
                    NoDevEnvUtil::verifyThatComposerJsonAndLockAreInSync();
                    NoDevEnvUtil::execComposerInstallShellCommand(withDev: true);
                }
            }
        );
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function runConfigurePhpTemplatesScript(): void
    {
        self::runCmdLineImpl(
            function (): void {
                if (NoDevEnvUtil::isCurrentOsWindows()) {
                    NoDevEnvUtil::log('<configure PHP templates> is not implemented (yet?) for Windows');
                } else {
                    NoDevEnvUtil::execShellCommand('./tools/build/configure_php_templates.sh');
                }
            }
        );
    }

//    /**
//     * This method is used in scripts section of composer.json
//     *
//     * @noinspection PhpUnused
//     */
//    public static function installNoDevUsingCurrentLock(Event $event): void
//    {
//        self::runCmdLineImpl(fn() => AdaptPackagesToPhp81::installNoDevUsingCurrentLock($event->getArguments()));
//    }

    /**
     * @param callable(): void $code
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function runCmdLineImpl(callable $code): void
    {
        $exitCode = 0;

        try {
            require NoDevEnvUtil::getRepoRootPath() . DIRECTORY_SEPARATOR
                . 'prod' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'ElasticOTel' . DIRECTORY_SEPARATOR . 'AutoloaderElasticOTelClasses.php';
            AutoloaderElasticOTelClasses::register('Elastic\\OTel', __DIR__);
            $code();
        } catch (Throwable $throwable) {
            $exitCode = self::FAILURE_EXIT_CODE;
            NoDevEnvUtil::log('Caught throwable');
            NoDevEnvUtil::logThrowable($throwable);
        }

        exit($exitCode);
    }

    private static function shouldAllowDirectCommand(): bool
    {
        $envVarVal = getenv(NoDevEnvUtil::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME);

        if (!is_string($envVarVal)) {
            return false;
        }

        return match (strtolower($envVarVal)) {
            'true', '1' => true,
            default => false,
        };
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function regularInstallOrUpdateNotSupported(): void
    {
        if (self::shouldAllowDirectCommand()) {
            return;
        }

        NoDevEnvUtil::log("Do not run `composer install' or `composer update' directly.");

        NoDevEnvUtil::log(
            "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer install'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . 'composer run-script -- install_dev_select_generated_lock'
            . "\n"
            . "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer update'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . './tools/build/generate_composer_lock_files.sh && composer run-script -- install_dev_select_generated_lock',
        );

        exit(self::FAILURE_EXIT_CODE);
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    private static function selectGeneratedDevLock(): void
    {
        NoDevEnvUtil::log('Selecting dev_<PHP version>.lock ...');

        self::copyComposeLockCurrentPhpVersion(NoDevEnvUtil::DEV_ENV_KIND);

        NoDevEnvUtil::log('Selected dev_<PHP version>.lock');
    }

    /** @noinspection PhpSameParameterValueInspection */
    private static function copyComposeLockCurrentPhpVersion(string $envKind): void
    {
        NoDevEnvUtil::log('Copying composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');

        $repoRootPath = NoDevEnvUtil::getRepoRootPath();
        $srcFilePath = NoDevEnvUtil::buildToGeneratedFileFullPath($repoRootPath, NoDevEnvUtil::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
        if (!file_exists($srcFilePath)) {
            throw new RuntimeException("File $srcFilePath does not exist");
        }

        $dstFilePath = $repoRootPath . DIRECTORY_SEPARATOR . 'composer.lock';

        NoDevEnvUtil::copyFile($srcFilePath, $dstFilePath);

        NoDevEnvUtil::log('Copied composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');
    }

    /**
     * @return list<string>
     */
    private static function getCommandLineArgs(Event $event): array
    {
        $eventArguments = $event->getArguments();
        NoDevEnvUtil::assertIsList($eventArguments, compact('eventArguments'));
        return $eventArguments;
    }

    public static function verifyGeneratedComposerLockFiles(): void
    {
        $repoRootPath = NoDevEnvUtil::getRepoRootPath();
        $repoRootJsonPath = $repoRootPath . DIRECTORY_SEPARATOR . NoDevEnvUtil::COMPOSER_JSON_FILE_NAME;
        $generatedDevJsonPath = NoDevEnvUtil::buildToGeneratedFileFullPath($repoRootPath, NoDevEnvUtil::buildGeneratedComposerJsonFileNameForPhpVersion(NoDevEnvUtil::DEV_ENV_KIND, 'not 81'));
        NoDevEnvUtil::assertFilesHaveSameContent($repoRootJsonPath, $generatedDevJsonPath);
    }
}
