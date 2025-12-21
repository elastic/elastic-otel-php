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
use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\IdGenerator;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentConfigFile;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentConfigMap;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentRemoteConfig;
use ElasticOTelTests\ComponentTests\Util\OTelUtil;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Attributes;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\FloatOptionParser;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdDefaultValues;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\HttpContentTypes;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\ListUtilForTests;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\ReflectionUtil;
use JsonException;
use OpenTelemetry\API\Behavior\Internal\Logging as OTelInternalLogging;
use OpenTelemetry\SDK\Common\Configuration\Defaults as OTelSdkConfigDefaults;
use OpenTelemetry\SDK\Common\Configuration\KnownValues as OTelSdkConfigKnownValues;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTelSdkConfigVariables;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler as OTelAlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler as OTelAlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased as OTelSamplerParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler as OTelTraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface as OTelSamplerInterface;
use OpenTelemetry\SDK\Trace\Tracer as OTelTracer;
use OpenTelemetry\SDK\Trace\TracerSharedState as OTelTracerSharedState;
use PHPUnit\Framework\Assert;

use function Elastic\OTel\get_remote_configuration;

/**
 * @group smoke
 * @group does_not_require_external_services
 *
 * @phpstan-import-type ElasticFileDecodedBody from RemoteConfigHandler
 * @phpstan-import-type AttributeValue from Attributes
 * @phpstan-import-type AttributesMapIterable from OTelUtil as OTelAttributesMapIterable
 */
final class RemoteConfigTest extends ComponentTestCaseBase
{
    private const SET_MOCK_AGENT_REMOTE_CONFIG_KEY = 'set_mock_agent_remote_config';
    private const SET_OPAMP_ENDPOINT_KEY = 'set_opamp_endpoint';
    private const REMOTE_CONFIG_MAP_KEY = 'remote_config_map';
    private const GET_REMOTE_CONFIGURATION_RESULT_KEY = 'get_remote_configuration_result';
    private const GET_REMOTE_CONFIGURATION_ELASTIC_RESULT_KEY = 'get_remote_configuration_elastic_result';
    private const LAST_APPLIED_ELASTIC_FILE_KEY = 'last_applied_elastic_file';

    private const REMOTE_CONFIG_LOGGING_LEVEL_KEY = 'remote_config_logging_level';
    private const ACTUAL_OTEL_LOG_LEVEL_KEY = 'actual_OTel_log_level';
    private const ACTUAL_ELASTIC_LOG_LEVEL_KEY = 'actual_Elastic_log_level';

    private const REMOTE_CONFIG_SAMPLING_RATE_KEY = 'remote_config_sampling_rate';
    private const ACTUAL_SAMPLER_KEY = 'actual_sampler';
    private const ACTUAL_SAMPLER_ARG_KEY = 'actual_sampler_arg';

    /**
     * @param callable(): AgentRemoteConfig $buildAgentRemoteConfig
     * @param callable(AppCodeHostParams $appCodeParams): void $configureAppCode
     * @param array{class-string, string} $appCodeClassMethod
     * @param callable(Span $lastSpan): void $assertResults
     */
    private function implTestOption(
        MixedMap $testArgs,
        callable $buildAgentRemoteConfig,
        callable $configureAppCode,
        array $appCodeClassMethod,
        callable $assertResults
    ): void {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }

        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs, $buildAgentRemoteConfig, $configureAppCode, $appCodeClassMethod, $assertResults): void {
                DebugContext::getCurrentScope(/* out */ $dbgCtx);

                $testCaseHandle = $this->getTestCaseHandle();

                /** @var ?AgentRemoteConfig $agentRemoteConfig */
                $agentRemoteConfig = null;
                if ($testArgs->tryToGetBool(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY) ?? true) {
                    $agentRemoteConfig = $buildAgentRemoteConfig();
                    $testCaseHandle->getMockOTelCollector()->setAgentRemoteConfig($agentRemoteConfig);
                }

                $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
                    function (AppCodeHostParams $appCodeParams) use ($testArgs, $testCaseHandle, $configureAppCode): void {
                        if ($testArgs->tryToGetBool(self::SET_OPAMP_ENDPOINT_KEY) ?? true) {
                            $appCodeParams->setProdOption(OptionForProdName::opamp_endpoint, $testCaseHandle->getMockOTelCollector()->buildOpampEndpointOptionValue());
                            $appCodeParams->setProdOption(OptionForProdName::opamp_heartbeat_interval, '1s');
                        }
                        $configureAppCode($appCodeParams);
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
                $dbgCtx->add(compact('agentBackendComms'));
                $lastSpan = ArrayUtilForTests::getLastValue(IterableUtil::toList($agentBackendComms->spans()));
                /** @var Span $lastSpan */
                $dbgCtx->add(compact('lastSpan'));
                $assertResults($lastSpan);
            }
        );
    }

    /**
     * @return iterable<string, array{MixedMap}>
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function dataProviderForTestHandlingRemoteConfig(): iterable
    {
        /**
         * @return iterable<AgentConfigMap>
         */
        $generateRemoteConfigMap = function (): iterable {
            $elasticFileName = RemoteConfigHandler::ELASTIC_FILE_NAME;
            $emptyMapJsonEncoded = "{}";
            $nonEmptyMapJsonEncoded = JsonUtil::encode(['opt_1_name' => 'opt_1_val', 'opt_2_name' => 'opt_2_val']);
            $elasticFileNameSandwiched = ['_' . $elasticFileName, $elasticFileName, $elasticFileName . '_'];
            $fileNameWithoutElastic = ['_' . $elasticFileName, $elasticFileName . '_'];
            $fileWithNonEmptyMapJsonEncoded = new AgentConfigFile(HttpContentTypes::JSON, $nonEmptyMapJsonEncoded);
            foreach ([[$elasticFileName], $elasticFileNameSandwiched, $fileNameWithoutElastic] as $fileNames) {
                foreach ([HttpContentTypes::JSON, 'dummy-content-type'] as $fileContentType) {
                    foreach ([$nonEmptyMapJsonEncoded, $emptyMapJsonEncoded] as $fileContent) {
                        $fileNameToVal = [];
                        foreach ($fileNames as $fileName) {
                            $fileNameToVal[$fileName] = $fileName === $elasticFileName ? new AgentConfigFile($fileContentType, $fileContent) : $fileWithNonEmptyMapJsonEncoded;
                        }
                        yield new AgentConfigMap($fileNameToVal);
                    }
                }
            }
        };

        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY)
                ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SET_OPAMP_ENDPOINT_KEY)
                ->addKeyedDimensionOnlyFirstValueCombinable(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE, [null, __DIR__ . '/Util/OTel_SDK_experimental_config_file.yaml'])
                ->addKeyedDimensionOnlyFirstValueCombinable(self::REMOTE_CONFIG_MAP_KEY, $generateRemoteConfigMap)
        );
    }

    /**
     * @return ?ElasticFileDecodedBody
     */
    private function decodeElasticConfigFileFromMap(AgentConfigFile $elasticConfigFile): ?array
    {
        if ($elasticConfigFile->contentType !== HttpContentTypes::JSON) {
            return null;
        }

        try {
            return AssertEx::isArray(JsonUtil::decode($elasticConfigFile->body, asAssocArray: true));
        } catch (JsonException) {
            return null;
        }
    }

    public static function appForTestHandlingRemoteConfig(): void
    {
        OTelUtil::addActiveSpanAttributes(
            [
                // get_remote_configuration() is implemented by the extension
                self::GET_REMOTE_CONFIGURATION_RESULT_KEY => JsonUtil::encode(get_remote_configuration()),
                self::GET_REMOTE_CONFIGURATION_ELASTIC_RESULT_KEY => AssertEx::isNullableString(get_remote_configuration(RemoteConfigHandler::ELASTIC_FILE_NAME)),
                self::LAST_APPLIED_ELASTIC_FILE_KEY => JsonUtil::encode(RemoteConfigHandler::getLastAppliedElasticFileDecodedBody()),
            ],
        );
    }

    /**
     * @dataProvider dataProviderForTestHandlingRemoteConfig
     */
    public function testHandlingRemoteConfig(MixedMap $testArgs): void
    {
        $remoteCfgMap = AssertEx::isInstanceOf($testArgs->get(self::REMOTE_CONFIG_MAP_KEY), AgentConfigMap::class);
        $declConfigFilePath = $testArgs->getNullableString(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE);

        $this->implTestOption(
            $testArgs,
            buildAgentRemoteConfig: function () use ($remoteCfgMap): AgentRemoteConfig {
                return new AgentRemoteConfig(config: $remoteCfgMap, configHash: IdGenerator::generateId(idLengthInBytes: 16));
            },
            configureAppCode: function (AppCodeHostParams $appCodeParams) use ($declConfigFilePath): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::experimental_config_file, $declConfigFilePath);
            },
            appCodeClassMethod: [__CLASS__, 'appForTestHandlingRemoteConfig'],
            assertResults: function (Span $lastSpan) use ($testArgs, $remoteCfgMap, $declConfigFilePath): void {
                $elasticConfigFile = ArrayUtil::getValueIfKeyExistsElse(RemoteConfigHandler::ELASTIC_FILE_NAME, $remoteCfgMap->configMap, null);
                /** @var ?AgentConfigFile $elasticConfigFile */
                $decodeElasticConfigFile = $elasticConfigFile === null ? null : self::decodeElasticConfigFileFromMap($elasticConfigFile);
                $isRemoteCfgExpectedToBeApplied =
                    $testArgs->getBool(self::SET_MOCK_AGENT_REMOTE_CONFIG_KEY)
                    && $testArgs->getBool(self::SET_OPAMP_ENDPOINT_KEY)
                    && ($decodeElasticConfigFile !== null)
                    && ($declConfigFilePath === null);

                $getRemoteConfigurationResultAttr = AssertEx::isArray(JsonUtil::decode($lastSpan->attributes->getString(self::GET_REMOTE_CONFIGURATION_RESULT_KEY), asAssocArray: true));
                AssertEx::equalMaps($remoteCfgMap->configMap, $getRemoteConfigurationResultAttr);
                self::assertSame($elasticConfigFile?->body, AssertEx::isNullableString($lastSpan->attributes->getValue(self::GET_REMOTE_CONFIGURATION_ELASTIC_RESULT_KEY)));
                if ($isRemoteCfgExpectedToBeApplied) {
                    AssertEx::equalMaps($decodeElasticConfigFile, AssertEx::isArray($lastSpan->attributes->getString(self::LAST_APPLIED_ELASTIC_FILE_KEY)));
                } else {
                    self::assertNull($lastSpan->attributes->getValue(self::LAST_APPLIED_ELASTIC_FILE_KEY));
                }
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

    private static function discoverOTelInternalLoggingLogLevelName(int $logLevelIndex): string
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
                self::ACTUAL_OTEL_LOG_LEVEL_KEY => self::discoverOTelInternalLoggingLogLevelName(OTelInternalLogging::logLevel()),
                self::ACTUAL_ELASTIC_LOG_LEVEL_KEY => LogLevel::from(BootstrapStageLogger::getMaxEnabledLevel())->name,
            ],
        );
    }

    /**
     * @return array{'OTel': string, 'Elastic': string}
     */
    private static function deriveExpectedLogLevels(?string $localOTelLevelRawVal, ?string $localElasticLevelRawVal, mixed $remoteCfgLevelRawVal): array
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
            configureAppCode: function (AppCodeHostParams $appCodeParams) use ($localOTelLevel, $localElasticLevel): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::log_level, $localOTelLevel);
                $appCodeParams->setProdOptionIfNotNull(self::elasticLogLevelOpt(), $localElasticLevel);
            },
            appCodeClassMethod: [__CLASS__, 'appForTestLoggingLevel'],
            assertResults: function (Span $lastSpan) use ($dbgCtx, $localOTelLevel, $localElasticLevel, $remoteCfgLevel): void {
                $expectedLevels = self::deriveExpectedLogLevels($localOTelLevel, $localElasticLevel, $remoteCfgLevel);
                $dbgCtx->add(compact('expectedLevels'));
                $expectedOTelLevel = $expectedLevels['OTel'];
                $expectedElasticLevel = $expectedLevels['Elastic'];
                self::assertSame($expectedOTelLevel, $lastSpan->attributes->getString(self::ACTUAL_OTEL_LOG_LEVEL_KEY));
                self::assertSame($expectedElasticLevel, $lastSpan->attributes->getString(self::ACTUAL_ELASTIC_LOG_LEVEL_KEY));
            }
        );
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestSamplingRate(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionOnlyFirstValueCombinable(self::REMOTE_CONFIG_SAMPLING_RATE_KEY, [0.1, "0", 1, 'dummy_sampling_rate', null])
                ->addKeyedDimensionOnlyFirstValueCombinable(
                    OTelSdkConfigVariables::OTEL_TRACES_SAMPLER,
                    [null, OTelSdkConfigKnownValues::VALUE_ALWAYS_ON, OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO, OTelSdkConfigKnownValues::VALUE_TRACE_ID_RATIO, 'dummy_sampler']
                )
                ->addKeyedDimensionOnlyFirstValueCombinable(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG, [null, 0.5])
        );
    }

    /**
     * @return OTelAttributesMapIterable
     */
    private static function discoverActualSamplerAndArg(): iterable
    {
        $tracer = OTelUtil::getTracer();
        self::assertInstanceOf(OTelTracer::class, $tracer);
        $tracerSharedState = ReflectionUtil::getPropertyValue($tracer, 'tracerSharedState');
        self::assertInstanceOf(OTelTracerSharedState::class, $tracerSharedState);
        $sampler = ReflectionUtil::getPropertyValue($tracerSharedState, 'sampler');
        self::assertInstanceOf(OTelSamplerInterface::class, $sampler);

        /**
         * @see \OpenTelemetry\SDK\Trace\SamplerFactory::create
         */

        $getSamplerArg = function (OTelSamplerInterface $sampler): ?float {
            if ($sampler instanceof OTelTraceIdRatioBasedSampler) {
                /**
                 * @see \OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler
                 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
                 */
                return AssertEx::isFloat(ReflectionUtil::getPropertyValue($sampler, 'probability'));
            }
            return null;
        };

        return match ($sampler::class) {
            OTelAlwaysOffSampler::class => [self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_ALWAYS_OFF, self::ACTUAL_SAMPLER_ARG_KEY => null],
            OTelAlwaysOnSampler::class => [self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_ALWAYS_ON, self::ACTUAL_SAMPLER_ARG_KEY => null],
            OTelSamplerParentBased::class => match (get_class($parentBasedRootSampler = AssertEx::isInstanceOf(ReflectionUtil::getPropertyValue($sampler, 'root'), OTelSamplerInterface::class))) {
                OTelAlwaysOffSampler::class => [self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_PARENT_BASED_ALWAYS_OFF, self::ACTUAL_SAMPLER_ARG_KEY => null],
                OTelAlwaysOnSampler::class => [self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_PARENT_BASED_ALWAYS_ON, self::ACTUAL_SAMPLER_ARG_KEY => null],
                OTelTraceIdRatioBasedSampler::class => [
                    self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO,
                    self::ACTUAL_SAMPLER_ARG_KEY => $getSamplerArg($parentBasedRootSampler),
                ],
                default => [self::ACTUAL_SAMPLER_KEY => $sampler::class . ',' . $parentBasedRootSampler::class, self::ACTUAL_SAMPLER_ARG_KEY => null],
            },
            OTelTraceIdRatioBasedSampler::class => [self::ACTUAL_SAMPLER_KEY => OTelSdkConfigKnownValues::VALUE_TRACE_ID_RATIO, self::ACTUAL_SAMPLER_ARG_KEY => $getSamplerArg($sampler)],
            default => [self::ACTUAL_SAMPLER_KEY => $sampler::class, self::ACTUAL_SAMPLER_ARG_KEY => null],
        };
    }

    /**
     * @return array{'sampler': string, 'sampler_arg': ?float}
     */
    private static function deriveExpectedSamplerAndArg(?string $localCfgSampler, ?float $localCfgSamplerArg, mixed $remoteCfgSamplingRate): array
    {
        $remoteCfgSamplingRateAsString = strval($remoteCfgSamplingRate); // @phpstan-ignore argument.type
        if (($localCfgSampler === null || $localCfgSampler === OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO) && FloatOptionParser::isValidFormat($remoteCfgSamplingRateAsString)) {
            return ['sampler' => OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO, 'sampler_arg' => floatval($remoteCfgSamplingRateAsString)];
        }

        return ['sampler' => $localCfgSampler ?? OTelSdkConfigDefaults::OTEL_TRACES_SAMPLER, 'sampler_arg' => $localCfgSamplerArg];
    }

    public static function appForTestSamplingRate(): void
    {
        OTelUtil::addActiveSpanAttributes(self::discoverActualSamplerAndArg());
    }

    /**
     * @dataProvider dataProviderForTestSamplingRate
     */
    public function testSamplingRate(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $remoteCfgSamplingRate = $testArgs->get(self::REMOTE_CONFIG_SAMPLING_RATE_KEY);
        $localCfgSampler = $testArgs->getNullableString(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER);
        $localCfgSamplerArg = $testArgs->getNullableFloat(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG);

        $this->implTestOption(
            $testArgs,
            buildAgentRemoteConfig: function () use ($remoteCfgSamplingRate): AgentRemoteConfig {
                return self::buildAgentRemoteConfig([RemoteConfigHandler::SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME => $remoteCfgSamplingRate]);
            },
            configureAppCode: function (AppCodeHostParams $appCodeParams) use ($localCfgSampler, $localCfgSamplerArg): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::sampler, $localCfgSampler);
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::sampler_arg, $localCfgSamplerArg);
            },
            appCodeClassMethod: [__CLASS__, 'appForTestSamplingRate'],
            assertResults: function (Span $lastSpan) use ($dbgCtx, $localCfgSampler, $localCfgSamplerArg, $remoteCfgSamplingRate): void {
                $expectedSamplerAndArg = self::deriveExpectedSamplerAndArg($localCfgSampler, $localCfgSamplerArg, $remoteCfgSamplingRate);
                $dbgCtx->add(compact('expectedSamplerAndArg'));
                self::assertSame($expectedSamplerAndArg['sampler'], $lastSpan->attributes->getString(self::ACTUAL_SAMPLER_KEY));
                self::assertSame($expectedSamplerAndArg['sampler_arg'], $lastSpan->attributes->getValue(self::ACTUAL_SAMPLER_ARG_KEY));
            }
        );
    }
}
