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

use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Config\ConfigException;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\FileUtil;
use Override;

final class TestInfraHttpServerStarter extends HttpServerStarter
{
    private string $runScriptName;
    private ?ResourcesCleanerHandle $resourcesCleaner;

    /**
     * @param int[] $portsInUse
     */
    public static function startTestInfraHttpServer(
        string $dbgServerDesc,
        string $runScriptName,
        array $portsInUse,
        int $portsToAllocateCount,
        ?ResourcesCleanerHandle $resourcesCleaner
    ): HttpServerHandle {
        return (new self($dbgServerDesc, $runScriptName, $resourcesCleaner))->startHttpServer($portsInUse, $portsToAllocateCount);
    }

    private function __construct(string $dbgServerDesc, string $runScriptName, ?ResourcesCleanerHandle $resourcesCleaner)
    {
        parent::__construct($dbgServerDesc);

        $this->runScriptName = $runScriptName;
        $this->resourcesCleaner = $resourcesCleaner;
    }

    /** @inheritDoc */
    #[Override]
    protected function buildCommandLine(array $ports): string
    {
        $runScriptNameFullPath = FileUtil::listToPath([__DIR__, $this->runScriptName]);
        if (!file_exists($runScriptNameFullPath)) {
            throw new ConfigException(ExceptionUtil::buildMessage('Run script does not exist', array_merge(['runScriptName' => $this->runScriptName], compact('runScriptNameFullPath'))));
        }

        return 'php ' . '"' . FileUtil::listToPath([__DIR__, $this->runScriptName]) . '"';
    }

    /** @inheritDoc */
    #[Override]
    protected function buildEnvVarsForSpawnedProcess(string $spawnedProcessInternalId, array $ports): array
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
            $this->resourcesCleaner,
            $this->dbgServerDesc
        );
    }
}
