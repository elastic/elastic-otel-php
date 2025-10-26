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

use ElasticOTelTests\Util\Config\ConfigException;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\FileUtil;
use Override;
use PHPUnit\Framework\Assert;

final class BuiltinHttpServerAppCodeHostStarter extends HttpServerStarter
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToBuiltinHttpServerAppCodeHost.php';

    private function __construct(
        private readonly HttpAppCodeHostParams $appCodeHostParams,
        private readonly ResourcesCleanerHandle $resourcesCleaner
    ) {
        parent::__construct($appCodeHostParams->dbgProcessNamePrefix);
    }

    /**
     * @param int[] $portsInUse
     */
    public static function startBuiltinHttpServerAppCodeHost(HttpAppCodeHostParams $appCodeHostParams, ResourcesCleanerHandle $resourcesCleaner, array $portsInUse): HttpServerHandle
    {
        return (new self($appCodeHostParams, $resourcesCleaner))->startHttpServer($portsInUse);
    }

    /** @inheritDoc */
    #[Override]
    protected function buildCommandLine(array $ports): string
    {
        Assert::assertCount(1, $ports);
        $routerScriptNameFullPath = FileUtil::listToPath([__DIR__, self::APP_CODE_HOST_ROUTER_SCRIPT]);
        if (!file_exists($routerScriptNameFullPath)) {
            throw new ConfigException(ExceptionUtil::buildMessage('Router script does not exist', compact('routerScriptNameFullPath')));
        }

        return InfraUtilForTests::buildAppCodePhpCmd()
               . ' -S ' . HttpServerHandle::SERVER_LOCALHOST_ADDRESS . ':' . $ports[0]
               . ' "' . $routerScriptNameFullPath . '"';
    }

    /** @inheritDoc */
    #[Override]
    protected function buildEnvVarsForSpawnedProcess(string $dbgProcessName, string $spawnedProcessInternalId, array $ports): array
    {
        Assert::assertCount(1, $ports);
        return InfraUtilForTests::addTestInfraDataPerProcessToEnvVars(
            $this->appCodeHostParams->buildEnvVarsForAppCodeProcess(),
            $spawnedProcessInternalId,
            $ports,
            $this->resourcesCleaner,
            $dbgProcessName
        );
    }
}
