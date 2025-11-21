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

use Elastic\OTel\Util\BoolUtil;

/**
 * @phpstan-import-type EnvVars from BuildToolsUtil
 */
final class InstallPhpDeps
{
    use BuildToolsAssertTrait;
    use BuildToolsLoggingClassTrait;

    public static function verifyGeneratedComposerLockFiles(): void
    {
        BuildToolsUtil::runCmdLineImpl(
            __METHOD__,
            function (): void {
                $repoRootDir = BuildToolsUtil::getCurrentDirectory();
                $repoRootJsonPath = $repoRootDir . DIRECTORY_SEPARATOR . ComposerUtil::COMPOSER_JSON_FILE_NAME;
                $generatedDevJsonPath = ComposerUtil::buildToGeneratedFileFullPath($repoRootDir, ComposerUtil::buildGeneratedComposerJsonFileName(PhpDepsEnvKind::dev));
                self::assertFilesHaveSameContent($repoRootJsonPath, $generatedDevJsonPath);
            }
        );
    }

    public static function selectDevLockAndInstall(): void
    {
        BuildToolsUtil::runCmdLineImpl(
            __METHOD__,
            function (): void {
                self::selectLockAndInstall(BuildToolsUtil::getCurrentDirectory(), PhpDepsEnvKind::dev, allowOverwrite: true);
            }
        );
    }

    /**
     * @param list<string> $cmdLineArgs
     */
    public static function selectJsonLockAndInstall(array $cmdLineArgs): void
    {
        BuildToolsUtil::runCmdLineImpl(
            __METHOD__,
            function () use ($cmdLineArgs): void {
                self::assertCount(1, $cmdLineArgs);
                $envKind = self::assertNotNull(PhpDepsEnvKind::tryToFindByName($cmdLineArgs[0]));
                $repoRootDir = BuildToolsUtil::getCurrentDirectory();
                $generatedJsonFile = ComposerUtil::buildToGeneratedFileFullPath($repoRootDir, ComposerUtil::buildGeneratedComposerJsonFileName($envKind));
                BuildToolsUtil::copyFile($generatedJsonFile, BuildToolsUtil::partsToPath($repoRootDir, ComposerUtil::COMPOSER_JSON_FILE_NAME));
                self::selectLockAndInstall($repoRootDir, $envKind, allowOverwrite: false);
            }
        );
    }

    private static function selectLockAndInstall(string $repoRootDir, PhpDepsEnvKind $envKind, bool $allowOverwrite): void
    {
        $generatedLockFile = ComposerUtil::buildToGeneratedFileFullPath($repoRootDir, ComposerUtil::buildGeneratedComposerLockFileNameForCurrentPhpVersion($envKind));
        BuildToolsUtil::copyFile($generatedLockFile, BuildToolsUtil::partsToPath($repoRootDir, ComposerUtil::COMPOSER_LOCK_FILE_NAME), allowOverwrite: $allowOverwrite);
        ComposerUtil::verifyThatComposerJsonAndLockAreInSync();

        $withDev = ComposerUtil::convertEnvKindToWithDev($envKind);
        if (AdaptPhpDepsTo81::isCurrentPhpVersion81() && ($envKind !== PhpDepsEnvKind::test)) {
            AdaptPhpDepsTo81::downloadAdaptPackagesGenConfigAndInstall($withDev);
        } else {
            ComposerUtil::execComposerInstallShellCommand($withDev, envVars: [ComposerUtil::ALLOW_DIRECT_COMPOSER_COMMAND_ENV_VAR_NAME => BoolUtil::toString(true)]);
        }
    }

    /**
     * Must be defined in class using BuildToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
