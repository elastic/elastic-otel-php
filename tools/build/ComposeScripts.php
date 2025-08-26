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

use RuntimeException;

/**
 * This class is used in scripts section of composer.json
 *
 * @noinspection PhpUnused
 */
final class ComposeScripts
{
    private static ?bool $isStderrDefined = null;

    private const ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME = 'ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND';

    /**
     * @see elastic_otel_php_build_tools_composer_lock_files_dir in tool/shared.sh
     */
    private const COMPOSER_LOCK_FILES_DIR_NAME = 'generated_composer_lock_files';

    private static function shouldAllowDirectCommand(): bool
    {
        $envVarVal = getenv(self::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME);

        if (!is_string($envVarVal)) {
            return false;
        }

        return match (strtolower($envVarVal)) {
            'true', '1' => true,
            default => false,
        };
    }

    private static function installOrUpdate(): void
    {
        if (self::shouldAllowDirectCommand()) {
            return;
        }

        self::log("Do not run `composer install' or `composer update' directly.");

        self::log(
            "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer install'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . 'composer run-script -- install-using-generated-lock-dev'
            . "\n"
            . "\n" . 'Instead of'
            . "\n" . "\t\t" . 'composer update'
            . "\n" . "\t" . 'run'
            . "\n" . "\t\t" . './tools/build/generate_composer_lock_files.sh && composer run-script -- install-using-generated-lock-dev'
        );

        exit(1);
    }

    /**
     * This function is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function install(): void
    {
        self::installOrUpdate();
    }

    /**
     * This function is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function update(): void
    {
        self::installOrUpdate();
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function prepareForInstallUsingGeneratedLockDev(): void
    {
        self::log('Preparing for compose install...');

        self::copyComposeLockCurrentPhpVersion('dev');

        self::log('Prepared for compose install');
    }

    /**
     * This method is used in scripts section of composer.json
     *
     * @noinspection PhpUnused
     */
    public static function prepareForInstallUsingGeneratedLockTests(): void
    {
        self::log('Preparing for compose install...');

        self::copyComposeLockCurrentPhpVersion('tests');

        self::log('Prepared for compose install');
    }

    private static function copyComposeLockCurrentPhpVersion(string $envKind): void
    {
        self::log('Copying composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');

        $repoRootPath = self::realFilePath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

        $srcFileName = self::buildComposerLockFileNameForCurrentPhpVersion($envKind);
        $srcFilePath = self::realFilePath($repoRootPath . DIRECTORY_SEPARATOR . self::COMPOSER_LOCK_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $srcFileName);
        if (!file_exists($srcFilePath)) {
            throw new RuntimeException("File $srcFilePath does not exist");
        }

        $dstFilePath = $repoRootPath . DIRECTORY_SEPARATOR . 'composer.lock';

        self::copyFile($srcFilePath, $dstFilePath);

        self::log('Copied composer\'s lock for env kind (' . $envKind . ') and the current PHP version (' . PHP_VERSION . ') to composer.lock');
    }

    private static function buildComposerLockFileNameForCurrentPhpVersion(string $envKind): string
    {
        /**
         * @see build_composer_lock_file_name_for_PHP_version() finction in tool/shared.sh
         */
        return $envKind . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.lock';
    }

    private static function realFilePath(string $path): string
    {
        $result = realpath($path);
        if ($result === false) {
            throw new RuntimeException("Failed to resolve path: `$path'");
        }
        return $result;
    }

    private static function copyFile(string $fromFilePath, string $toFilePath): void
    {
        self::log('Copying file ' . $fromFilePath . ' to ' . $toFilePath . '...');

        // \copy works incorrectly on some PHP versions
        // and produces an empty destination file instead of copying the contents
        // if (!copy($from, $to)) {
        //     throw new RuntimeException("Failed to copy file from `$from'' to `$to'");
        // }

        $fromFileSize = self::getFileSize($fromFilePath);
        $fromFileContents = self::getFileContents($fromFilePath);
        if (strlen($fromFileContents) !== $fromFileSize) {
            throw new RuntimeException("File contents length does not match file size; file path: `$fromFilePath'; file size: $fromFileSize; contents length: " . strlen($fromFileContents));
        }

        $toFileContentsWrittenSize = self::putFileContents($toFilePath, $fromFileContents);
        if ($toFileContentsWrittenSize !== $fromFileSize) {
            throw new RuntimeException(
                "Length of contents written does not match contents length; file path: `$fromFilePath'; contents length: $fromFileSize; written length: $toFileContentsWrittenSize"
            );
        }

        $toFileContents = self::getFileContents($toFilePath);
        if ($fromFileContents !== $toFileContents) {
            throw new RuntimeException(
                "Written contents does not match the original; file: {from: `$fromFilePath'; to: `$toFilePath'}"
                . "; contents length {from: $fromFileSize; to: " . strlen($toFileContents) . "}"
                . "\n" . "contents:"
                . "\n" . "from:"
                . "\n" . $fromFileContents
                . "\n" . "to:"
                . "\n" . $toFileContents
            );
        }

        self::log('Copied file ' . $fromFilePath . ' to ' . $toFilePath . '...');
    }

    private static function getFileContents(string $filePath): string
    {
        $result = file_get_contents($filePath);
        if (!is_string($result)) {
            throw new RuntimeException("Failed to get file contents; file path: `$filePath'");
        }
        return $result;
    }

    private static function putFileContents(string $filePath, string $contents): int
    {
        $result = file_put_contents($filePath, $contents);
        if (!is_int($result)) {
            throw new RuntimeException("Failed to put file contents; file path: `$filePath'; contents length: " . strlen($contents));
        }
        return $result;
    }

    private static function getFileSize(string $filePath): int
    {
        $result = filesize($filePath);
        if (!is_int($result)) {
            throw new RuntimeException("Failed to get file size; file path: `$filePath'");
        }
        return $result;
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
