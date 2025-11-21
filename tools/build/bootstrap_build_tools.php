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

use Elastic\OTel\AutoloaderElasticOTelClasses;
use Elastic\OTel\BootstrapStageLogger;
use Elastic\OTel\Log\LogLevel;
use RuntimeException;

const ELASTIC_OTEL_PHP_TOOLS_LOG_LEVEL_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TOOLS_LOG_LEVEL';

require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsAssertTrait.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsLog.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'BuildToolsLoggingClassTrait.php';

// __DIR__ is "<repo root>/tools/build"
$repoRootDir = realpath($repoRootDirTempVal = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($repoRootDir === false) {
    throw new RuntimeException("realpath returned false for $repoRootDirTempVal");
}
$prodPhpElasticOTelPath = $repoRootDir . DIRECTORY_SEPARATOR . 'prod' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'ElasticOTel';
require $prodPhpElasticOTelPath . DIRECTORY_SEPARATOR . 'Util' . DIRECTORY_SEPARATOR . 'EnumUtilTrait.php';
require $prodPhpElasticOTelPath . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'LogLevel.php';
require $prodPhpElasticOTelPath . DIRECTORY_SEPARATOR . 'BootstrapStageStdErrWriter.php';
require $prodPhpElasticOTelPath . DIRECTORY_SEPARATOR . 'BootstrapStageLogger.php';

$getMaxEnabledLogLevelConfig = function (): ?LogLevel {
    $envVarVal = getenv(ELASTIC_OTEL_PHP_TOOLS_LOG_LEVEL_ENV_VAR_NAME);
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
BootstrapStageLogger::configure($maxEnabledLogLevel->value, $prodPhpElasticOTelPath, __NAMESPACE__, $writeToSinkForBootstrapStageLogger);

require $prodPhpElasticOTelPath . DIRECTORY_SEPARATOR . 'AutoloaderElasticOTelClasses.php';
AutoloaderElasticOTelClasses::register('Elastic\\OTel', $prodPhpElasticOTelPath);
AutoloaderElasticOTelClasses::register(__NAMESPACE__, __DIR__);
