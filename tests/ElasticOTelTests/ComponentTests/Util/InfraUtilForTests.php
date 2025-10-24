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

namespace ElasticOTelTests\ComponentTests\Util;

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Config\IniRawSnapshotSource;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\EnvVarUtil;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 */
final class InfraUtilForTests
{
    use StaticClassTrait;

    public static function generateSpawnedProcessInternalId(): string
    {
        return IdGenerator::generateId(idLengthInBytes: 16);
    }

    /**
     * @param int[] $targetServerPorts
     */
    public static function buildTestInfraDataPerProcess(string $targetSpawnedProcessInternalId, array $targetServerPorts, ?ResourcesCleanerHandle $resourcesCleaner): TestInfraDataPerProcess
    {
        $currentProcessId = getmypid();
        if ($currentProcessId === false) {
            throw new ComponentTestsInfraException('Failed to get current process ID');
        }

        return new TestInfraDataPerProcess(
            rootProcessId:                            $currentProcessId,
            resourcesCleanerSpawnedProcessInternalId: $resourcesCleaner?->spawnedProcessInternalId,
            resourcesCleanerPort:                     $resourcesCleaner?->getMainPort(),
            thisSpawnedProcessInternalId:             $targetSpawnedProcessInternalId,
            thisServerPorts:                          $targetServerPorts
        );
    }

    /**
     * @phpstan-param EnvVars $baseEnvVars
     * @phpstan-param int[]   $targetServerPorts
     *
     * @return EnvVars
     */
    public static function addTestInfraDataPerProcessToEnvVars(
        array $baseEnvVars,
        string $targetSpawnedProcessInternalId,
        array $targetServerPorts,
        ?ResourcesCleanerHandle $resourcesCleaner,
        string $dbgProcessName
    ): array {
        $dataPerProcessEnvVarName = OptionForTestsName::data_per_process->toEnvVarName();
        $dataPerProcess = self::buildTestInfraDataPerProcess($targetSpawnedProcessInternalId, $targetServerPorts, $resourcesCleaner);
        $result = $baseEnvVars;
        $additionalEnvVars = [
            SpawnedProcessBase::DBG_PROCESS_NAME_ENV_VAR_NAME => $dbgProcessName,
            $dataPerProcessEnvVarName                         => PhpSerializationUtil::serializeToString($dataPerProcess),
        ];
        ArrayUtilForTests::append(from: $additionalEnvVars, to: $result);
        ksort(/* ref */ $result);
        return $result;
    }

    public static function buildAppCodePhpCmd(): string
    {
        $result = AmbientContextForTests::testConfig()->appCodePhpExe ?? 'php';

        if (($extBinary = AmbientContextForTests::testConfig()->appCodeExtBinary) !== null) {
            $result .= ' -d "extension=' . $extBinary . '"';
        }

        if (($bootstrapPhpPartFile = AmbientContextForTests::testConfig()->appCodeBootstrapPhpPartFile) !== null) {
            $bootstrapPhpPartFileIniOptName = IniRawSnapshotSource::DEFAULT_PREFIX . OptionForProdName::bootstrap_php_part_file->name;
            $result .= ' -d "' . $bootstrapPhpPartFileIniOptName . '=' . $bootstrapPhpPartFile . '"';
        }

        return $result;
    }

    /**
     * @param int[] $ports
     *
     * @return EnvVars
     */
    public static function buildEnvVarsForSpawnedProcessWithoutAppCode(string $dbgProcessName, string $spawnedProcessInternalId, array $ports, ?ResourcesCleanerHandle $resourcesCleaner): array
    {
        $baseEnvVars = EnvVarUtilForTests::getAll();
        $additionalEnvVars = [
            OptionForProdName::autoload_enabled->toEnvVarName()          => BoolUtil::toString(false),
            OptionForProdName::disabled_instrumentations->toEnvVarName() => ConfigUtilForTests::PROD_DISABLED_INSTRUMENTATIONS_ALL,
            OptionForProdName::enabled->toEnvVarName()                   => BoolUtil::toString(false),
        ];
        ArrayUtilForTests::append(from: $additionalEnvVars, to: $baseEnvVars);

        return InfraUtilForTests::addTestInfraDataPerProcessToEnvVars(
            $baseEnvVars,
            $spawnedProcessInternalId,
            $ports,
            $resourcesCleaner,
            $dbgProcessName
        );
    }
}
