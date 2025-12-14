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

namespace ElasticOTelTests\ComponentTests;

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\RemoteConfigHandler;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\MixedMap;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class RemoteConfigTest extends ComponentTestCaseBase
{
    // private const MAX_WAIT_FOR_CONFIG_TO_BE_APPLIED_IN_SECONDS = 60; // 1 minute
    // private const SLEEP_BETWEEN_ATTEMPTS_WAITING_FOR_CONFIG_TO_BE_APPLIED_SECONDS = 5;

    private const LOGGING_LEVEL_KEY = 'logging_level';

    private static function elasticLogLevelOpt(): OptionForProdName
    {
        return OptionForProdName::log_level_file;
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestLoggingLevel(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionAllValuesCombinable(
                    RemoteConfigHandler::OTEL_LOG_LEVEL_OPTION_NAME,
                    [null, 'dummy_OTel_log_level', PsrLogLevel::WARNING]
                )
                ->addKeyedDimensionAllValuesCombinable(
                    self::elasticLogLevelOpt()->toEnvVarName(),
                    [null, 'dummy_EDOT_log_level', LogLevel::off->name, LogLevel::critical->name, LogLevel::trace->name]
                )
                ->addKeyedDimensionAllValuesCombinable(
                    self::LOGGING_LEVEL_KEY,
                    array_merge(array_keys(RemoteConfigHandler::LOGGING_LEVEL_TO_OTEL), ['dummy_logging_level', null])
                )
        );
    }

    private function implTestLoggingLevel(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $otelLevel = $testArgs->getNullableString(RemoteConfigHandler::OTEL_LOG_LEVEL_OPTION_NAME);
        $elasticLevel = $testArgs->getNullableString(self::elasticLogLevelOpt()->toEnvVarName());
        $remoteCfgLevel = $testArgs->getNullableString(self::LOGGING_LEVEL_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $testCaseHandle->getMockOTelCollector()->setRemoteConfigFileNameToContent(
            self::buildRemoteConfigFileNameToContent(
                [
                    RemoteConfigHandler::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME => $remoteCfgLevel
                ]
            )
        );

        /**
         * TODO: Sergey Kleyman: REMOVE: PhpUnusedLocalVariableInspection $appCodeHost
         * @noinspection PhpUnusedLocalVariableInspection
         */
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($otelLevel, $elasticLevel): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::log_level, $otelLevel);
                $appCodeParams->setProdOptionIfNotNull(self::elasticLogLevelOpt(), $elasticLevel);
                self::disableTimingDependentFeatures($appCodeParams);
            }
        );
    }

    /**
     * @dataProvider dataProviderForTestLoggingLevel
     */
    public function testLoggingLevel(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestLoggingLevel($testArgs);
            }
        );
    }
}
