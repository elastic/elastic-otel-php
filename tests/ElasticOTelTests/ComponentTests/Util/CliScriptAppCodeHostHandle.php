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

use Closure;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\ConfigException;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;

final class CliScriptAppCodeHostHandle extends AppCodeHostHandle
{
    private readonly Logger $logger;

    /**
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     */
    public function __construct(
        TestCaseHandle $testCaseHandle,
        Closure $setParamsFunc,
        private readonly ResourcesCleanerHandle $resourcesCleaner,
        string $dbgInstanceName
    ) {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $appCodeHostParams = new AppCodeHostParams(dbgProcessNamePrefix: ClassNameUtil::fqToShort(CliScriptAppCodeHost::class) . '_' . $dbgInstanceName);
        $appCodeHostParams->spawnedProcessInternalId = InfraUtilForTests::generateSpawnedProcessInternalId();
        $setParamsFunc($appCodeHostParams);

        parent::__construct($testCaseHandle, $appCodeHostParams);

        $this->logger->addAllContext(compact('this'));
    }

    public static function getRunScriptNameFullPath(): string
    {
        return FileUtil::listToPath([__DIR__, CliScriptAppCodeHost::SCRIPT_TO_RUN_APP_CODE_HOST]);
    }

    /** @inheritDoc */
    #[Override]
    public function execAppCode(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $localLogger = $this->logger->inherit()->addAllContext(compact('appCodeTarget'));
        $loggerProxyDebug = $localLogger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $requestParams = new AppCodeRequestParams($this->appCodeHostParams->spawnedProcessInternalId, $appCodeTarget);
        if ($setParamsFunc !== null) {
            $setParamsFunc($requestParams);
        }
        $localLogger->addAllContext(compact('requestParams'));

        $runScriptNameFullPath = self::getRunScriptNameFullPath();
        if (!file_exists($runScriptNameFullPath)) {
            throw new ConfigException(ExceptionUtil::buildMessage('Run script does not exist', compact('runScriptNameFullPath')));
        }

        $cmdLine = InfraUtilForTests::buildAppCodePhpCmd() . ' "' . $runScriptNameFullPath . '"';
        $localLogger->addAllContext(compact('cmdLine'));

        $dbgProcessName = DbgProcessNameGenerator::generate($this->appCodeHostParams->dbgProcessNamePrefix);
        $localLogger->addAllContext(compact('dbgProcessName'));

        $envVars = InfraUtilForTests::addTestInfraDataPerProcessToEnvVars(
            $this->appCodeHostParams->buildEnvVarsForAppCodeProcess(),
            $this->appCodeHostParams->spawnedProcessInternalId,
            [] /* <- targetServerPorts */,
            $this->resourcesCleaner,
            $dbgProcessName
        );
        $envVars[OptionForTestsName::data_per_request->toEnvVarName()] = PhpSerializationUtil::serializeToString($requestParams->dataPerRequest);
        ksort(/* ref */ $envVars);
        $localLogger->addAllContext(compact('envVars'));

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Executing app code ...');

        $appCodeInvocation = $this->beforeAppCodeInvocation($requestParams);
        SpawnedProcessBase::startProcessAndWaitForItToExit($dbgProcessName, $cmdLine, $envVars);
        $this->afterAppCodeInvocation($appCodeInvocation);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Executed app code');
    }
}
