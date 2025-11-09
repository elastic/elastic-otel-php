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

use Elastic\OTel\PhpPartFacade;

/**
 * @phpstan-import-type EnvVars from BuildToolsUtil
 */
final class ComposerUtil
{
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    public const ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND';

    public const COMPOSER_JSON_FILE_NAME = 'composer.json';
    public const COMPOSER_LOCK_FILE_NAME = 'composer.lock';

    private const COMPOSER_INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS =
        '--ignore-platform-req=ext-mysqli'
        . ' '
        . '--ignore-platform-req=ext-pgsql'
        . ' '
        . '--ignore-platform-req=ext-opentelemetry'
    ;

    /**
     * @see elastic_otel_php_build_tools_composer_lock_files_dir in tool/shared.sh
     */
    private const GENERATED_FILES_DIR_NAME = 'generated_composer_lock_files';

    public static function shouldAllowDirectCommand(): bool
    {
        return PhpPartFacade::getBoolEnvVar(self::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME, default: false);
    }

    /**
     * @param EnvVars $envVars
     */
    public static function execComposerInstallShellCommand(bool $withDev, string $additionalArgs = '', array $envVars = []): void
    {
        $cmdParts = [];
        $cmdParts[] = self::convertEnvVarsToCmdLinePart($envVars);
        $cmdParts[] = 'composer ' . self::COMPOSER_INSTALL_CMD_IGNORE_PLATFORM_REQ_ARGS . ' --no-interaction';
        $cmdParts[] = $withDev ? '' : '--no-dev';
        $cmdParts[] = $additionalArgs;
        $cmdParts[] = 'install';
        BuildToolsUtil::execShellCommand(BuildToolsUtil::buildShellCommand($cmdParts));
    }

    public static function buildToGeneratedFileFullPath(string $repoRootPath, string $fileName): string
    {
        return BuildToolsFileUtil::realPath($repoRootPath . DIRECTORY_SEPARATOR . self::GENERATED_FILES_DIR_NAME . DIRECTORY_SEPARATOR . $fileName);
    }

    public static function buildGeneratedComposerJsonFileName(PhpDepsEnvKind $envKind): string
    {
        /**
         * @see build_generated_composer_json_file_name() finction in tool/shared.sh
         */

        return $envKind->name . '.json';
    }

    public static function buildGeneratedComposerLockFileNameForCurrentPhpVersion(PhpDepsEnvKind $envKind): string
    {
        /**
         * @see build_generated_composer_lock_file_name() finction in tool/shared.sh
         */
        return $envKind->name . '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.lock';
    }

    public static function verifyThatComposerJsonAndLockAreInSync(): void
    {
        BuildToolsUtil::execShellCommand('composer --check-lock --no-check-all validate');
    }

    public static function convertEnvKindToWithDev(PhpDepsEnvKind $envKind): bool
    {
        return match ($envKind) {
            PhpDepsEnvKind::dev, PhpDepsEnvKind::test => true,
            PhpDepsEnvKind::prod => false,
        };
    }

    /**
     * @param EnvVars $envVars
     */
    private static function convertEnvVarsToCmdLinePart(array $envVars): string
    {
        $cmdParts = [];
        foreach ($envVars as $envVarName => $envVarVal) {
            $cmdParts[] = BuildToolsUtil::isCurrentOsWindows() ? "set \"$envVarName=$envVarVal\" &&" : "$envVarName=\"$envVarVal\"";
        }
        return BuildToolsUtil::buildShellCommand($cmdParts);
    }

    /**
     * Must be defined in class using BuildToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
