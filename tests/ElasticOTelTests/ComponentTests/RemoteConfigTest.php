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

use Elastic\OTel\BootstrapStageLogger;
use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Log\OTelInternalLogLevel;
use Elastic\OTel\Log\PsrLogLevel;
use Elastic\OTel\Log\RemoteConfigLoggingLevel;
use Elastic\OTel\RemoteConfigHandler;
use ElasticOTelTests\ComponentTests\Util\AgentBackendComms;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentRemoteConfig;
use ElasticOTelTests\ComponentTests\Util\OTelUtil;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\BoolUtilForTests;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdDefaultValues;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\ListUtilForTests;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\ReflectionUtil;
use OpenTelemetry\API\Behavior\Internal\Logging as OTelInternalLogging;
use PHPUnit\Framework\Assert;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class RemoteConfigTest extends ComponentTestCaseBase
{
    // private const MAX_WAIT_FOR_CONFIG_TO_BE_APPLIED_IN_SECONDS = 60; // 1 minute
    // private const SLEEP_BETWEEN_ATTEMPTS_WAITING_FOR_CONFIG_TO_BE_APPLIED_SECONDS = 5;

    private const SET_MOCK_AGENT_REMOTE_CONFIG_KEY = 'set_mock_agent_remote_config';
    private const SET_OPAMP_ENDPOINT_KEY = 'set_opamp_endpoint';

    private const REMOTE_CONFIG_LOGGING_LEVEL_KEY = 'remote_config_logging_level';

    private const OTEL_INTERNAL_LOGGING_LOG_LEVEL_KEY = 'otel_internal_logging_log_level';
    private const ELASTIC_LOG_LEVEL_KEY = 'elastic_log_level';

    /**
     * @param callable(): AgentRemoteConfig $buildAgentRemoteConfig
     * @param callable(AppCodeHostParams $appCodeParams): void $callConfigureAppCode
     * @param array{class-string, string} $appCodeClassMethod
     * @param callable(AgentBackendComms $agentBackendComms): void $assertResults
     *
     * TODO: Sergey Kleyman: REMOVE: PhpSameParameterValueInspection
     * @noinspection PhpSameParameterValueInspection
     */
    private function implTestOption(
        MixedMap $testArgs,
        callable $buildAgentRemoteConfig,
        callable $callConfigureAppCode,
        array $appCodeClassMethod,
        callable $assertResults
    ): void {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }

        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs, $buildAgentRemoteConfig, $callConfigureAppCode, $appCodeClassMethod, $assertResults): void {
                $testCaseHandle = $this->getTestCaseHandle();

                /** @var ?AgentRemoteConfig $agentRemoteConfig */
                $agentRemoteConfig = null;
                if ($testArgs->getBool(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY)) {
                    $agentRemoteConfig = $buildAgentRemoteConfig();
                    $testCaseHandle->getMockOTelCollector()->setAgentRemoteConfig($agentRemoteConfig);
                }

                $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
                    function (AppCodeHostParams $appCodeParams) use ($testArgs, $testCaseHandle, $callConfigureAppCode): void {
                        if ($testArgs->getBool(self::SET_OPAMP_ENDPOINT_KEY)) {
                            $appCodeParams->setProdOption(OptionForProdName::opamp_endpoint, $testCaseHandle->getMockOTelCollector()->buildOpampEndpointOptionValue());
                            $appCodeParams->setProdOption(OptionForProdName::opamp_heartbeat_interval, '1s');
                        }
                        $callConfigureAppCode($appCodeParams);
                        self::disableTimingDependentFeatures($appCodeParams);
                    }
                );

                $execAppCode = function () use ($appCodeHost, $appCodeClassMethod, $testArgs): void {
                    $appCodeHost->execAppCode(
                        AppCodeTarget::asRouted($appCodeClassMethod),
                        function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                            $appCodeRequestParams->setAppCodeArgs($testArgs);
                        }
                    );
                };
                // Invoke app code the first time to make sure agent is running
                $execAppCode();
                $expectedSpansCount = 1;

                if ($agentRemoteConfig !== null) {
                    $testCaseHandle->waitForAgentToApplyRemoteConfig($agentRemoteConfig->configHash);
                    // Invoke app code the second time after the agent applied remote configuration
                    $execAppCode();
                    ++$expectedSpansCount;
                }

                $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans($expectedSpansCount));
                $assertResults($agentBackendComms);
            }
        );
    }

    private static function elasticLogLevelOpt(): OptionForProdName
    {
        return OptionForProdName::log_level_syslog;
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestLoggingLevel(): iterable
    {
        $remoteLevelValidVariantsOffFirst = ListUtilForTests::bringToFront(RemoteConfigLoggingLevel::trace->name, RemoteConfigLoggingLevel::casesNames());
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionAllValuesCombinable(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY, BoolUtilForTests::allValuesStartingFrom(true))
                ->addKeyedDimensionAllValuesCombinable(self::SET_OPAMP_ENDPOINT_KEY, BoolUtilForTests::allValuesStartingFrom(true))
                ->addKeyedDimensionOnlyFirstValueCombinable(
                    self::REMOTE_CONFIG_LOGGING_LEVEL_KEY,
                    [...$remoteLevelValidVariantsOffFirst, 'dummy_logging_level', null, 34.5]
                )
                ->addKeyedDimensionOnlyFirstValueCombinable(
                    RemoteConfigHandler::OTEL_LOG_LEVEL_OPTION_NAME,
                    [null, PsrLogLevel::emergency->name, PsrLogLevel::warning->name]
                )
                ->addKeyedDimensionOnlyFirstValueCombinable(
                    self::elasticLogLevelOpt()->toEnvVarName(),
                    [null, LogLevel::error->name, LogLevel::debug->name]
                )
        );
    }

    /**
     * @return list<string>
     */
    private static function allOTelInternalLoggingLogLevelNames(): array
    {
        /** @var ?list<string> $cachedValue */
        static $cachedValue = null;
        if ($cachedValue === null) {
            $cachedValue = AssertEx::isList(AssertEx::isArray(ReflectionUtil::getConstValue(OTelInternalLogging::class, 'LEVELS')));
        }
        /** @var list<string> $cachedValue */
        return $cachedValue;
    }

    private static function findOTelInternalLoggingLogLevelName(int $logLevelIndex): string
    {
        foreach (self::allOTelInternalLoggingLogLevelNames() as $logLevelName) {
            if (OTelInternalLogging::level($logLevelName) === $logLevelIndex) {
                return $logLevelName;
            }
        }
        Assert::fail("There is no OTelInternalLogging level for index $logLevelIndex");
    }

    public static function appForTestLoggingLevel(): void
    {
        OTelUtil::addActiveSpanAttributes(
            [
                self::OTEL_INTERNAL_LOGGING_LOG_LEVEL_KEY => self::findOTelInternalLoggingLogLevelName(OTelInternalLogging::logLevel()),
                self::ELASTIC_LOG_LEVEL_KEY => LogLevel::from(BootstrapStageLogger::getMaxEnabledLevel())->name,
            ],
        );
    }

    /**
     * @return array{'OTel': string, 'Elastic': string}
     */
    private static function buildExpectedLogLevels(?string $localOTelLevelRawVal, ?string $localElasticLevelRawVal, mixed $remoteCfgLevelRawVal): array
    {
        if (is_string($remoteCfgLevelRawVal) && ($remoteCfgLevel = RemoteConfigLoggingLevel::tryToFindByName($remoteCfgLevelRawVal)) !== null) {
            $expectedOTelLevel = $remoteCfgLevel->toOTelInternalLogLevel()->name;
            $expectedElasticLevel = $remoteCfgLevel->toElasticLogLevel()->name;
        }

        if ($localOTelLevelRawVal !== null) {
            $expectedOTelLevel ??= OTelInternalLogLevel::tryToFindByName($localOTelLevelRawVal)?->name;
        }
        if ($localElasticLevelRawVal !== null) {
            $expectedElasticLevel ??= LogLevel::tryToFindByName($localElasticLevelRawVal)?->name;
        }

        $expectedOTelLevel ??= OptionsForProdDefaultValues::LOG_LEVEL->name;
        $expectedElasticLevel ??= LogLevelUtil::defaultProdElasticLogLevel()->name;

        return ['OTel' => $expectedOTelLevel, 'Elastic' => $expectedElasticLevel];
    }

    /**
     * @dataProvider dataProviderForTestLoggingLevel
     */
    public function testLoggingLevel(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $remoteCfgLevel = $testArgs->get(self::REMOTE_CONFIG_LOGGING_LEVEL_KEY);
        $localOTelLevel = $testArgs->getNullableString(RemoteConfigHandler::OTEL_LOG_LEVEL_OPTION_NAME);
        $localElasticLevel = $testArgs->getNullableString(self::elasticLogLevelOpt()->toEnvVarName());

        $this->implTestOption(
            $testArgs,
            buildAgentRemoteConfig: function () use ($remoteCfgLevel): AgentRemoteConfig {
                return self::buildAgentRemoteConfig([RemoteConfigHandler::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME => $remoteCfgLevel]);
            },
            callConfigureAppCode: function (AppCodeHostParams $appCodeParams) use ($localOTelLevel, $localElasticLevel): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::log_level, $localOTelLevel);
                $appCodeParams->setProdOptionIfNotNull(self::elasticLogLevelOpt(), $localElasticLevel);
            },
            appCodeClassMethod: [__CLASS__, 'appForTestLoggingLevel'],
            assertResults: function (AgentBackendComms $agentBackendComms) use ($testArgs, $dbgCtx, $localOTelLevel, $localElasticLevel, $remoteCfgLevel): void {
                $remoteCfgLevelToConsider = ($testArgs->getBool(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY) && $testArgs->getBool(self::SET_OPAMP_ENDPOINT_KEY)) ? $remoteCfgLevel : null;
                $dbgCtx->add(compact('remoteCfgLevelToConsider'));
                $expectedLevels = self::buildExpectedLogLevels($localOTelLevel, $localElasticLevel, $remoteCfgLevelToConsider);
                $dbgCtx->add(compact('expectedLevels'));
                $expectedOTelLevel = $expectedLevels['OTel'];
                $expectedElasticLevel = $expectedLevels['Elastic'];
                /** @var Span $lastSpan */
                $lastSpan = ArrayUtilForTests::getLastValue(IterableUtil::toList($agentBackendComms->spans()));
                $dbgCtx->add(compact('lastSpan'));
                self::assertSame($expectedOTelLevel, $lastSpan->attributes->getString(self::OTEL_INTERNAL_LOGGING_LOG_LEVEL_KEY));
                self::assertSame($expectedElasticLevel, $lastSpan->attributes->getString(self::ELASTIC_LOG_LEVEL_KEY));
            }
        );
    }
}
