<?php

declare(strict_types=1);

namespace OpenTelemetry\DistroTools\Build;

use OpenTelemetry\Distro\AutoloaderDistroOTelClasses;
use OpenTelemetry\Distro\BootstrapStageLogger;
use OpenTelemetry\Distro\Log\LogLevel;
use RuntimeException;

const OTEL_PHP_TOOLS_LOG_LEVEL_ENV_VAR_NAME = 'OTEL_PHP_TOOLS_LOG_LEVEL';

require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsAssertTrait.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsLog.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsLoggingClassTrait.php';

// __DIR__ is "<repo root>/tools/build"
$repoRootDir = realpath($repoRootDirTempVal = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($repoRootDir === false) {
    throw new RuntimeException("realpath returned false for $repoRootDirTempVal");
}
$prodPhpDistroPath = $repoRootDir . DIRECTORY_SEPARATOR . 'upstream' . DIRECTORY_SEPARATOR . 'prod' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'OpenTelemetry' . DIRECTORY_SEPARATOR . 'Distro';
require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'Util' . DIRECTORY_SEPARATOR . 'EnumUtilTrait.php';
require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'LogLevel.php';
require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'BootstrapStageStdErrWriter.php';
require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'BootstrapStageLogger.php';
require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'BootstrapStageLoggingClassTrait.php';

$getMaxEnabledLogLevelConfig = function (): ?LogLevel {
    $envVarVal = getenv(OTEL_PHP_TOOLS_LOG_LEVEL_ENV_VAR_NAME);
    if (!is_string($envVarVal)) {
        return null;
    }

    return LogLevel::tryToFindByName(strtolower($envVarVal));
};
$maxEnabledLogLevel = $getMaxEnabledLogLevelConfig() ?? BuildToolsLog::DEFAULT_LEVEL;
BuildToolsLog::configure($maxEnabledLogLevel);

$writeToSinkForBootstrapStageLogger = function (int $level, int $feature, string $file, int $line, string $func, string $text): void {
    BuildToolsLog::writeAsProdSink($level, $feature, $file, $line, $func, $text);
};
BootstrapStageLogger::configure($maxEnabledLogLevel->value, $prodPhpDistroPath, __NAMESPACE__, $writeToSinkForBootstrapStageLogger);

require $prodPhpDistroPath . DIRECTORY_SEPARATOR . 'AutoloaderDistroOTelClasses.php';
AutoloaderDistroOTelClasses::register('OpenTelemetry\\Distro', $prodPhpDistroPath);
AutoloaderDistroOTelClasses::register(__NAMESPACE__, __DIR__);
