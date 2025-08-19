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

use RuntimeException;

/**
 * This class is used in scripts section of composer.json
 *
 * @noinspection PhpUnused
 */
final class ComposeScripts
{
    private static ?bool $isStderrDefined = null;

    /** @noinspection PhpNoReturnAttributeCanBeAddedInspection */
    private static function installOrUpdate(): void
    {
        self::log("Do not run `composer install' or `composer update' directly.");

        self::log(
            "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer install'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . 'composer run-script -- prepare-and-install'
            . "\n"
            . "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer update'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . './tools/build/generate_composer_lock_files.sh && composer run-script -- prepare-and-install'
        );

        exit(1);
    }

    /** @noinspection PhpNoReturnAttributeCanBeAddedInspection */
    public static function install(): void
    {
        self::installOrUpdate();
    }

    /** @noinspection PhpNoReturnAttributeCanBeAddedInspection */
    public static function update(): void
    {
        self::installOrUpdate();
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function prepareForInstall(): void
    {
        self::log('Preparing for compose install...');

        self::copyComposeLockForCurrentVersion();

        self::log('Prepared for compose install');
    }

    private static function copyComposeLockForCurrentVersion(): void
    {
        self::log('Copying for composer\'s lock for the current PHP version (' . PHP_VERSION . ') to composer.lock ...');

        $repoRootPath = self::realFilePath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

        /**
         * @see build_composer_lock_file_name_for_PHP_version() finction in tool/shared.sh
         */
        $srcFileName = 'composer_lock_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
        $srcFilePath = self::realFilePath($repoRootPath . DIRECTORY_SEPARATOR . $srcFileName);
        if (!file_exists($srcFilePath)) {
            throw new RuntimeException("File $srcFilePath does not exist");
        }

        $dstFilePath = $repoRootPath . DIRECTORY_SEPARATOR . 'composer.lock';

        self::copyFile($srcFilePath, $dstFilePath);

        self::log('Copied for composer\'s lock for the current PHP version (' . PHP_VERSION . ') to composer.lock');
    }

    private static function realFilePath(string $path): string
    {
        $result = realpath($path);
        if ($result === false) {
            throw new RuntimeException("Failed to resolve path: `$path'");
        }
        return $result;
    }

    private static function copyFile(string $from, string $to): void
    {
        if (!copy($from, $to)) {
            throw new RuntimeException("Failed to copy file from `$from'' to `$to'");
        }
    }

    private static function log(string $text): void
    {
        self::writeLineToStdErr($text);
    }

    private static function ensureStdErrIsDefined(): bool
    {
        if (self::$isStderrDefined === null) {
            if (defined('STDERR')) {
                self::$isStderrDefined = true;
            } else {
                define('STDERR', fopen('php://stderr', 'w'));
                self::$isStderrDefined = defined('STDERR');
            }
        }

        return self::$isStderrDefined;
    }

    /** @noinspection PhpUnused */
    public static function writeLineToStdErr(string $text): void
    {
        if (self::ensureStdErrIsDefined()) {
            fwrite(STDERR, $text . PHP_EOL);
        }
    }
}
