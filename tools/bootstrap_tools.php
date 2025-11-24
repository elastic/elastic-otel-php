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

namespace ElasticOTelTools;

use Elastic\OTel\AutoloaderElasticOTelClasses;
use Elastic\OTel\BootstrapStageLogger;
use Elastic\OTel\Log\LogLevel;
use RuntimeException;

require __DIR__ . '/bootstrap_shared.php';

// __DIR__ is "<repo root>/tools"
$repoRootDir = realpath($repoRootDirTempVal = __DIR__ . DIRECTORY_SEPARATOR . '..');
if ($repoRootDir === false) {
    throw new RuntimeException("realpath returned false for $repoRootDirTempVal");
}

$prodPhpElasticOTelPath = $repoRootDir . '/prod/php/ElasticOTel';
require $prodPhpElasticOTelPath . '/Util/EnumUtilTrait.php';
require $prodPhpElasticOTelPath . '/Log/LogLevel.php';
require $prodPhpElasticOTelPath . '/BootstrapStageStdErrWriter.php';
require $prodPhpElasticOTelPath . '/BootstrapStageLogger.php';

require __DIR__ . '/ToolsAssertTrait.php';
require __DIR__ . '/ToolsLog.php';
require __DIR__ . '/ToolsLoggingClassTrait.php';

$getMaxEnabledLogLevel = function (string $envVarName, LogLevel $default): LogLevel {
    $envVarVal = getenv($envVarName);
    if (!is_string($envVarVal)) {
        return $default;
    }

    return LogLevel::tryToFindByName(strtolower($envVarVal)) ?? $default;
};

ToolsLog::configure($getMaxEnabledLogLevel('ELASTIC_OTEL_PHP_TOOLS_LOG_LEVEL', default: LogLevel::info));

$prodLogLevel = $getMaxEnabledLogLevel('ELASTIC_OTEL_LOG_LEVEL_STDERR', default: LogLevel::info);

$writeToSinkForBootstrapStageLogger = function (int $level, int $feature, string $file, int $line, string $func, string $text): void {
    ToolsLog::writeAsProdSink($level, $feature, $file, $line, $func, $text);
};
BootstrapStageLogger::configure($prodLogLevel->value, $prodPhpElasticOTelPath, $writeToSinkForBootstrapStageLogger);

require $prodPhpElasticOTelPath . '/AutoloaderElasticOTelClasses.php';
AutoloaderElasticOTelClasses::register('Elastic\\OTel', $prodPhpElasticOTelPath);
AutoloaderElasticOTelClasses::register(__NAMESPACE__, __DIR__);
