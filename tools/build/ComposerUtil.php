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
use Elastic\OTel\PhpPartFacade;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsLog;
use ElasticOTelTools\ToolsUtil;

/**
 * @phpstan-import-type EnvVars from ToolsUtil
 */
final class ComposerUtil
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    public const ALLOW_DIRECT_COMMAND_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND';

    public const JSON_FILE_NAME_NO_EXT = 'composer';
    public const JSON_FILE_EXT = 'json';
    public const LOCK_FILE_EXT = 'lock';
    public const JSON_FILE_NAME = self::JSON_FILE_NAME_NO_EXT . '.' . self::JSON_FILE_EXT;
    public const LOCK_FILE_NAME = self::JSON_FILE_NAME_NO_EXT . '.' . self::LOCK_FILE_EXT;
    public const VENDOR_DIR_NAME = 'vendor';

    public const JSON_PHP_KEY = 'php';
    public const JSON_REQUIRE_KEY = 'require';
    public const JSON_REQUIRE_DEV_KEY = 'require-dev';

    public const HOME_ENV_VAR_NAME = 'COMPOSER_HOME';
    public const HOME_CONFIG_JSON_FILE_NAME = 'config.json';

    private const INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS =
        '--ignore-platform-req=ext-mysqli'
        . ' '
        . '--ignore-platform-req=ext-pgsql'
        . ' '
        . '--ignore-platform-req=ext-opentelemetry'
    ;

    public static function shouldAllowDirectCommand(): bool
    {
        return PhpPartFacade::getBoolEnvVar(self::ALLOW_DIRECT_COMMAND_ENV_VAR_NAME, default: false);
    }

    /**
     * @phpstan-param EnvVars $envVars
     *
     * @link https://getcomposer.org/doc/03-cli.md#install-i
     */
    public static function execInstall(bool $withDev, string $additionalArgs = '', array $envVars = []): void
    {
        $logLevel = LogLevel::info;
        if (ToolsLog::isLevelEnabled($logLevel)) {
            self::logWithLevel($logLevel, __LINE__, __METHOD__, 'Current directory: ' . ToolsUtil::getCurrentDirectory());
            ToolsUtil::listDirectoryContents(ToolsUtil::getCurrentDirectory());
            ToolsUtil::listFileContents(ToolsUtil::partsToPath(ToolsUtil::getCurrentDirectory(), ComposerUtil::JSON_FILE_NAME));
        }
        $cmdParts = ['composer ' . self::INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS . ' --no-interaction'];
        $cmdParts[] = $withDev ? '' : '--no-dev'; // --dev is deprecated and installing packages listed in require-dev is the default behavior
        $cmdParts[] = $additionalArgs;
        $cmdParts[] = 'install';
        self::execCommand(ToolsUtil::buildShellCommand($cmdParts, $envVars));
    }

    /**
     * @link https://getcomposer.org/doc/03-cli.md#dump-autoload-dumpautoload
     */
    public static function execDumpAutoLoad(bool $withDev, bool $classmapAuthoritative): void
    {
        $cmdParts = ['composer --no-interaction --optimize'];
        $cmdParts[] = $withDev ? '--dev' : '--no-dev';
        $cmdParts[] = $classmapAuthoritative ? '--classmap-authoritative' : '';
        $cmdParts[] = 'dump-autoload';
        self::execCommand(ToolsUtil::buildShellCommand($cmdParts));
    }

    /**
     * @phpstan-param list<string> $packagesToRemove
     * @phpstan-param EnvVars $envVars
     *
     * @link https://getcomposer.org/doc/03-cli.md#remove-rm-uninstall
     */
    public static function execRemove(array $packagesToRemove, string $additionalArgs = '', array $envVars = []): void
    {
        $cmdParts = ['composer ' . self::INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS . ' --no-interaction'];
        $cmdParts[] = $additionalArgs;
        $cmdParts[] = 'remove';
        self::execCommand(ToolsUtil::buildShellCommand(array_merge($cmdParts, $packagesToRemove), $envVars));
    }

    /**
     * @phpstan-param string|array<string> $cmdOrParts
     * @phpstan-param EnvVars $envVars
     */
    public static function execCommand(string|array $cmdOrParts, array $envVars = []): void
    {
        ToolsUtil::execShellCommand(
            ToolsUtil::buildShellCommand(is_string($cmdOrParts) ? [$cmdOrParts] : $cmdOrParts, $envVars),
            ['current directory' => ToolsUtil::getCurrentDirectory()]
        );
    }

    public static function verifyThatComposerJsonAndLockAreInSync(): void
    {
        self::execCommand('composer --check-lock --no-check-all validate');
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
