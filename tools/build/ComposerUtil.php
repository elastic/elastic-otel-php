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
use Elastic\OTel\Util\ListUtil;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLog;
use ElasticOTelTools\ToolsUtil;

/**
 * @phpstan-import-type EnvVars from ToolsUtil
 *
 * @phpstan-type PackageNameToVersionMap array<string, string>
 */
final class ComposerUtil
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    private const COMPOSER_CMD_LINE_EXE = 'composer';

    private const CLASSMAP_AUTHORITATIVE_CMD_LINE_OPT = '--classmap-authoritative';
    private const DEV_CMD_LINE_OPT = '--dev';
    private const NO_DEV_CMD_LINE_OPT = '--no-dev';
    private const NO_INSTALL_CMD_LINE_OPT = '--no-install';
    private const NO_INTERACTION_CMD_LINE_OPT = '--no-interaction';
    public const NO_PLUGINS_CMD_LINE_OPT = '--no-plugins';
    private const NO_SCRIPTS_CMD_LINE_OPT = '--no-scripts';
    private const OPTIMIZE_CMD_LINE_OPT = '--optimize';
    private const IGNORE_PLATFORM_CMD_LINE_OPTS = [
        '--ignore-platform-req=ext-mysqli',
        '--ignore-platform-req=ext-pgsql',
        '--ignore-platform-req=ext-opentelemetry',
    ];

    public const JSON_FILE_NAME_NO_EXT = 'composer';
    public const JSON_FILE_EXT = 'json';
    public const LOCK_FILE_EXT = 'lock';
    public const JSON_FILE_NAME = self::JSON_FILE_NAME_NO_EXT . '.' . self::JSON_FILE_EXT;
    public const LOCK_FILE_NAME = self::JSON_FILE_NAME_NO_EXT . '.' . self::LOCK_FILE_EXT;
    public const VENDOR_DIR_NAME = 'vendor';

    public const JSON_PHP_KEY = 'php';
    public const REQUIRE_KEY = 'require';
    public const REQUIRE_DEV_KEY = 'require-dev';

    public const HOME_ENV_VAR_NAME = 'COMPOSER_HOME';
    public const HOME_CONFIG_JSON_FILE_NAME = 'config.json';

    /**
     * @return list<string>
     */
    private static function startCmdParts(): array
    {
        return [self::COMPOSER_CMD_LINE_EXE, self::NO_INTERACTION_CMD_LINE_OPT];
    }

    /**
     * @phpstan-param list<string> $additionalArgs
     * @phpstan-param EnvVars $envVars
     *
     * @link https://getcomposer.org/doc/03-cli.md#install-i
     */
    public static function execInstall(bool $withDev, array $additionalArgs = [], array $envVars = []): void
    {
        self::logInfo(__LINE__, __METHOD__, 'Current directory: ' . ToolsUtil::getCurrentDirectory());

        $logLevelToListContents = LogLevel::debug;
        if (ToolsLog::isLevelEnabled($logLevelToListContents)) {
            ToolsUtil::listDirectoryContents(ToolsUtil::getCurrentDirectory());
            ToolsUtil::listFileContents(ToolsUtil::partsToPath(ToolsUtil::getCurrentDirectory(), ComposerUtil::JSON_FILE_NAME));
        }

        /** @var list<string> $cmdParts */
        $cmdParts = self::startCmdParts();
        $cmdParts[] = self::NO_SCRIPTS_CMD_LINE_OPT;
        ListUtil::append(self::IGNORE_PLATFORM_CMD_LINE_OPTS, $cmdParts);
        if (!$withDev) {
            $cmdParts[] = self::NO_DEV_CMD_LINE_OPT; // --dev is deprecated and installing packages listed in require-dev is the default behavior
        }
        ListUtil::append($additionalArgs, /* ref */ $cmdParts);
        $cmdParts[] = 'install';
        self::execCommand($cmdParts, $envVars);

        if (ToolsLog::isLevelEnabled($logLevelToListContents)) {
            ToolsUtil::listDirectoryContents(ToolsUtil::getCurrentDirectory());
            ToolsUtil::listFileContents(ToolsUtil::partsToPath(ToolsUtil::getCurrentDirectory(), ComposerUtil::JSON_FILE_NAME));
        }
    }

    /**
     * @link https://getcomposer.org/doc/03-cli.md#dump-autoload-dumpautoload
     */
    public static function execDumpAutoLoad(bool $withDev, bool $classmapAuthoritative): void
    {
        $cmdParts = self::startCmdParts();
        $cmdParts[] = self::OPTIMIZE_CMD_LINE_OPT;
        $cmdParts[] = $withDev ? self::DEV_CMD_LINE_OPT : self::NO_DEV_CMD_LINE_OPT;
        if ($classmapAuthoritative) {
            $cmdParts[] = self::CLASSMAP_AUTHORITATIVE_CMD_LINE_OPT;
        }
        $cmdParts[] = 'dump-autoload';
        self::execCommand($cmdParts);
    }

    private const MAX_COMMAND_LINE_LENGTH = 1000;

    /**
     * @phpstan-param list<string> $packagesToRemove
     * @phpstan-param list<string> $additionalArgs
     * @phpstan-param EnvVars $envVars
     *
     * @link https://getcomposer.org/doc/03-cli.md#remove-rm-uninstall
     */
    public static function execRemove(array $packagesToRemove, bool $inDev, array $additionalArgs = [], array $envVars = []): void
    {
        $cmdParts1stPart = self::startCmdParts();
        ListUtil::append(self::IGNORE_PLATFORM_CMD_LINE_OPTS, $cmdParts1stPart);
        $cmdParts1stPart[] = self::NO_SCRIPTS_CMD_LINE_OPT;
        ListUtil::append($additionalArgs, /* ref */ $cmdParts1stPart);
        $cmdParts1stPart[] = $inDev ? ComposerUtil::DEV_CMD_LINE_OPT : '--update-no-dev';
        $cmdParts1stPart[] = 'remove';

        $cmdParts2ndPart = [];
        foreach ($packagesToRemove as $packageName) {
            $cmdParts2ndPart[] = $packageName;
            $cmdPartsAll = ListUtil::concat($cmdParts1stPart, $cmdParts2ndPart);
            if (strlen(ToolsUtil::buildShellCommand($cmdPartsAll)) < self::MAX_COMMAND_LINE_LENGTH) {
                continue;
            }
            self::execCommand($cmdPartsAll, $envVars);
            $cmdParts2ndPart = [];
        }
        if (!ArrayUtil::isEmpty($cmdParts2ndPart)) {
            self::execCommand(ListUtil::concat($cmdParts1stPart, $cmdParts2ndPart), $envVars);
        }

        self::execCommand(ListUtil::concat($cmdParts1stPart, $packagesToRemove), $envVars);
    }

    /**
     * @phpstan-param list<string> $packagesToRemove
     */
    public static function removeFromComposerJsonAndLock(array $packagesToRemove, bool $inDev): void
    {
        // --no-update: Disables the automatic update of the dependencies (implies --no-install)
        self::execRemove($packagesToRemove, $inDev, additionalArgs: [ComposerUtil::NO_INSTALL_CMD_LINE_OPT, '--unused', '--minimal-changes']);
    }

    /**
     * @phpstan-param list<string> $additionalArgs
     * @phpstan-param EnvVars $envVars
     *
     * @link https://getcomposer.org/doc/03-cli.md#remove-rm-uninstall
     */
    public static function execUpdate(array $additionalArgs = [], array $envVars = []): void
    {
        $cmdParts = self::startCmdParts();
        ListUtil::append(self::IGNORE_PLATFORM_CMD_LINE_OPTS, $cmdParts);
        $cmdParts[] = self::NO_SCRIPTS_CMD_LINE_OPT;
        ListUtil::append($additionalArgs, /* ref */ $cmdParts);
        $cmdParts[] = 'update';
        self::execCommand($cmdParts, $envVars);
    }

    /**
     * @phpstan-param list<string> $additionalArgs
     * @phpstan-param EnvVars $envVars
     */
    public static function generateLock(array $additionalArgs = [], array $envVars = []): void
    {
        self::execUpdate(ListUtil::concat([ComposerUtil::NO_INSTALL_CMD_LINE_OPT], $additionalArgs), $envVars);
    }

    /**
     * @phpstan-param array<string> $cmdParts
     * @phpstan-param EnvVars $envVars
     */
    public static function execCommand(array $cmdParts, array $envVars = []): void
    {
        ToolsUtil::execShellCommand(ToolsUtil::buildShellCommand($cmdParts, $envVars), dbgCtx: ['current directory' => ToolsUtil::getCurrentDirectory()]);
    }

    public static function verifyThatComposerJsonAndLockAreInSync(): void
    {
        // Verify that composer.lock file is there because composer validate just skips .lock check if there is no composer.lock
        self::assertFileExists(ToolsUtil::partsToPath(ToolsUtil::getCurrentDirectory(), ComposerUtil::LOCK_FILE_NAME));
        self::execCommand(ListUtil::concat(self::startCmdParts(), ['--check-lock', '--no-check-all', '--strict', 'validate']));
    }

    public static function execRunScript(string $scriptName): void
    {
        self::execCommand(ListUtil::concat(self::startCmdParts(), ['run-script', '--', $scriptName]));
    }

    /**
     * @phpstan-param array<array-key, mixed> $requireSection
     *
     * @phpstan-return PackageNameToVersionMap
     */
    private static function readPackagesVersionsFromJsonSection(array $requireSection): array
    {
        $result = [];
        foreach ($requireSection as $packageName => $packageVersion) {
            self::assertIsString($packageName);
            self::assertIsString($packageVersion);
            $result[$packageName] = $packageVersion;
        }
        return $result;
    }

    /**
     * @phpstan-return array{'require': PackageNameToVersionMap, 'require-dev': PackageNameToVersionMap}
     */
    public static function readPackagesVersionsFromJson(string $filePath): array
    {
        $decodedJson = ToolsUtil::decodeJson(ToolsUtil::getFileContents($filePath));

        $result = [];
        foreach ([ComposerUtil::REQUIRE_KEY, ComposerUtil::REQUIRE_DEV_KEY] as $sectionName) {
            $result[$sectionName] = self::readPackagesVersionsFromJsonSection(self::assertIsArray(self::assertArrayHasKey($sectionName, $decodedJson)));
        }
        return $result;
    }

    private const PACKAGES_KEY = 'packages';
    private const PACKAGES_DEV_KEY = 'packages-dev';
    private const NAME_KEY = 'name';
    private const VERSION_KEY = 'version';

    /**
     * @phpstan-param array<array-key, mixed> $section
     *
     * @phpstan-return PackageNameToVersionMap
     */
    private static function readPackagesVersionsFromLockSection(array $packagesSection): array
    {
        $result = [];
        foreach ($packagesSection as $packageProps) {
            self::assertIsArray($packageProps);
            $packageName = self::assertIsString(self::assertArrayHasKey(self::NAME_KEY, $packageProps));
            $packageVersion = self::assertIsString(self::assertArrayHasKey(self::VERSION_KEY, $packageProps));
            if (ArrayUtil::getValueIfKeyExists($packageName, $result, /* out */ $alreadyPresentPackageVersion)) {
                self::assertSame($alreadyPresentPackageVersion, $packageVersion);
            } else {
                $result[$packageName] = $packageVersion;
            }
        }
        return $result;
    }

    /**
     * @phpstan-return array{'packages': PackageNameToVersionMap, 'packages-dev': PackageNameToVersionMap}
     */
    public static function readPackagesVersionsFromLock(string $filePath): array
    {
        $decodedJson = ToolsUtil::decodeJson(ToolsUtil::getFileContents($filePath));

        $result = [];
        foreach ([ComposerUtil::PACKAGES_KEY, ComposerUtil::PACKAGES_DEV_KEY] as $sectionName) {
            $result[$sectionName] = self::readPackagesVersionsFromLockSection(self::assertIsArray(self::assertArrayHasKey($sectionName, $decodedJson)));
        }
        return $result;
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
