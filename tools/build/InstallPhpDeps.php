<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

use OpenTelemetry\Distro\Util\BoolUtil;

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
