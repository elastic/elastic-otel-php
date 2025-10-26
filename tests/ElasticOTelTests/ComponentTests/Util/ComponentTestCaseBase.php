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

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\CompositeRawSnapshotSource;
use ElasticOTelTests\Util\Config\ConfigSnapshotForProd;
use ElasticOTelTests\Util\Config\EnvVarsRawSnapshotSource;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdMetadata;
use ElasticOTelTests\Util\Config\Parser as ConfigParser;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\RangeUtil;
use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SemConv\Version;
use Override;
use PHPUnit\Framework\Assert;
use Throwable;

class ComponentTestCaseBase extends TestCaseBase
{
    public const LOG_LEVEL_FOR_PROD_CODE_KEY = 'log_level_for_prod_code';
    public const LOG_LEVEL_FOR_TEST_CODE_KEY = 'log_level_for_test_code';
    protected const LOG_LEVEL_FOR_CODE_KEYS = [self::LOG_LEVEL_FOR_PROD_CODE_KEY, self::LOG_LEVEL_FOR_TEST_CODE_KEY];

    protected const SHOULD_APP_CODE_CREATE_DUMMY_SPAN_KEY = 'should_app_code_create_dummy_span';
    protected const APP_CODE_DUMMY_SPAN_NAME = 'app_code_dummy_span_name';

    protected const SHOULD_APP_CODE_SET_HOW_FINISHED_ATTRIBUTES_KEY = 'should_app_code_set_how_finished_attribute';
    protected const DID_APP_CODE_FINISH_SUCCESSFULLY_KEY = 'is_app_code_finished_successfully';
    protected const THROWABLE_FROM_APP_CODE_KEY = 'throwable_from_app_code';

    private ?TestCaseHandle $testCaseHandle = null;

    protected function initTestCaseHandle(?LogLevel $escalatedLogLevelForProdCode = null): TestCaseHandle
    {
        if ($this->testCaseHandle !== null) {
            return $this->testCaseHandle;
        }
        $this->testCaseHandle = new TestCaseHandle($escalatedLogLevelForProdCode);
        return $this->testCaseHandle;
    }

    protected function tearDownTestCaseHandle(): void
    {
        if ($this->testCaseHandle !== null) {
            $this->testCaseHandle->tearDown();
            $this->testCaseHandle = null;
        }
    }

    protected function getTestCaseHandle(): TestCaseHandle
    {
        return $this->initTestCaseHandle();
    }

    #[Override]
    public function tearDown(): void
    {
        $this->tearDownTestCaseHandle();

        parent::tearDown();
    }

    public static function appCodeEmpty(): void
    {
    }

    /**
     * @param callable(): void $appCodeImpl
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function appCodeSetsHowFinishedAttributes(MixedMap $appCodeArgs, ?callable $appCodeImpl = null): void
    {
        $shouldSetAttributes = $appCodeArgs->isBoolIsNotSetOrSetToTrue(self::SHOULD_APP_CODE_SET_HOW_FINISHED_ATTRIBUTES_KEY);

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logger->addAllContext(compact('shouldSetAttributes', 'appCodeArgs'));

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Calling $appCodeImpl() ...');
        try {
            if ($appCodeImpl !== null) {
                $appCodeImpl();
            }
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Call to $appCodeImpl() finished successfully');
            if ($shouldSetAttributes) {
                OTelUtil::addActiveSpanAttributes([self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => true]);
            }
        } catch (Throwable $throwable) {
            $loggerProxyDebug && $loggerProxyDebug->logThrowable(__LINE__, $throwable, 'Call to $appCodeImpl() thrown');
            if ($shouldSetAttributes) {
                OTelUtil::addActiveSpanAttributes([self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => false, self::THROWABLE_FROM_APP_CODE_KEY => LoggableToString::convert($throwable)]);
            }
            throw $throwable;
        }
    }

    public static function appCodeCreatesDummySpan(MixedMap $appCodeArgs): void
    {
        if ($appCodeArgs->isBoolIsNotSetOrSetToTrue(self::SHOULD_APP_CODE_CREATE_DUMMY_SPAN_KEY)) {
            OTelUtil::startEndSpan(OTelUtil::getTracer(), self::APP_CODE_DUMMY_SPAN_NAME);
        }
    }

    protected static function buildResourcesClientForAppCode(): ResourcesClient
    {
        $resCleanerId = AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerSpawnedProcessInternalId;
        Assert::assertNotNull($resCleanerId);
        $resCleanerPort = AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerPort;
        Assert::assertNotNull($resCleanerPort);
        return new ResourcesClient($resCleanerId, $resCleanerPort);
    }

    public static function isSmoke(): bool
    {
        return AmbientContextForTests::testConfig()->isSmoke();
    }

    public static function isMainAppCodeHostHttp(): bool
    {
        return AmbientContextForTests::testConfig()->appCodeHostKind()->isHttp();
    }

    protected function skipIfMainAppCodeHostIsNotCliScript(): bool
    {
        if (self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected function skipIfMainAppCodeHostIsNotHttp(): bool
    {
        if (!self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected static function waitForOneSpan(TestCaseHandle $testCaseHandle): Span
    {
        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans(1));
        return $agentBackendComms->singleSpan();
    }

    /**
     * @template T
     *
     * @param iterable<T> $variants
     *
     * @return iterable<T>
     */
    public static function adaptToSmoke(iterable $variants): iterable
    {
        if (!self::isSmoke()) {
            return $variants;
        }
        foreach ($variants as $key => $value) {
            if (ArrayUtilForTests::isOfArrayKeyType($key)) {
                return [$key => $value];
            } else {
                return [$value];
            }
        }
        return [];
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $variants
     *
     * @return iterable<TKey, TValue>
     */
    public function adaptKeyValueToSmoke(iterable $variants): iterable
    {
        if (!self::isSmoke()) {
            return $variants;
        }
        foreach ($variants as $key => $value) {
            return [$key => $value];
        }
        return [];
    }

    /**
     * @return callable(iterable<mixed>): iterable<mixed>
     */
    public static function adaptToSmokeAsCallable(): callable
    {
        /**
         * @template T
         *
         * @param iterable<T> $dataSets
         *
         * @return iterable<T>
         */
        return function (iterable $dataSets): iterable {
            return self::adaptToSmoke($dataSets);
        };
    }

    /**
     * @param callable(): iterable<array<string, mixed>> $dataSetsGenerator
     *
     * @return iterable<string, array{MixedMap}>
     */
    public static function adaptDataSetsGeneratorToSmokeToDescToMixedMap(callable $dataSetsGenerator): iterable
    {
        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc(fn() => self::adaptToSmoke($dataSetsGenerator()));
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(DataProviderForTestBuilder $dataProviderForTestBuilder): iterable
    {
        return self::adaptDataSetsGeneratorToSmokeToDescToMixedMap(fn() => $dataProviderForTestBuilder->buildWithoutDataSetName()); // @phpstan-ignore argument.type
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function dataProviderOneBoolArgAdaptedToSmoke(): iterable
    {
        return self::adaptToSmoke(self::dataProviderOneBoolArg());
    }

    /**
     * @param callable(): void $testCall
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function runAndEscalateLogLevelOnFailure(string $dbgTestDesc, callable $testCall): void
    {
        $logLevelForTestCodeToRestore = AmbientContextForTests::testConfig()->logLevel;
        try {
            $this->runAndEscalateLogLevelOnFailureImpl($dbgTestDesc, $testCall);
        } finally {
            AmbientContextForTests::resetLogLevel($logLevelForTestCodeToRestore);
        }
    }

    /**
     * @param callable(): void $testCall
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function runAndEscalateLogLevelOnFailureImpl(string $dbgTestDesc, callable $testCall): void
    {
        try {
            $testCall();
            return;
        } catch (Throwable $ex) {
            $initiallyFailedTestException = $ex;
        }

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('dbgTestDesc', 'initiallyFailedTestException'));
        $loggerProxyOutsideIt = $logger->ifCriticalLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test case code exited by exception');

        if ($this->testCaseHandle === null) {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test failed but $this->testCaseHandle is null - NOT re-running the test with escalated log levels');
            throw $initiallyFailedTestException;
        }
        $initiallyFailedTestLogLevels = $this->getCurrentLogLevels($this->testCaseHandle);
        if (ArrayUtilForTests::isEmpty($initiallyFailedTestLogLevels)) {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test failed but not even one app code host has started successfully - NOT re-running the test with escalated log levels');
            throw $initiallyFailedTestException;
        }
        $logger->addAllContext(compact('initiallyFailedTestLogLevels'));
        $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'Test failed');

        $escalatedLogLevelsSeq = self::generateLevelsForRunAndEscalateLogLevelOnFailure($initiallyFailedTestLogLevels, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
        $rerunCount = 0;
        foreach ($escalatedLogLevelsSeq as $escalatedLogLevels) {
            $this->tearDownTestCaseHandle();

            ++$rerunCount;
            $loggerPerIt = $logger->inherit()->addAllContext(compact('rerunCount', 'escalatedLogLevels'));
            $loggerProxyPerIt = $loggerPerIt->ifCriticalLevelEnabledNoLine(__FUNCTION__);

            $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-running failed test with escalated log levels...');

            AmbientContextForTests::resetLogLevel($escalatedLogLevels[self::LOG_LEVEL_FOR_TEST_CODE_KEY]);
            $this->initTestCaseHandle($escalatedLogLevels[self::LOG_LEVEL_FOR_PROD_CODE_KEY]);

            try {
                $testCall();
                $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-run of failed test with escalated log levels did NOT fail (which is bad :(');
            } catch (Throwable $ex) {
                $loggerProxyPerIt && $loggerProxyPerIt->log(__LINE__, 'Re-run of failed test with escalated log levels failed (which is good :)', compact('ex'));
                throw $ex;
            }
        }

        if ($rerunCount === 0) {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'There were no test re-runs with escalated log levels - re-throwing original test failure exception');
        } else {
            $loggerProxyOutsideIt && $loggerProxyOutsideIt->log(__LINE__, 'All test re-runs with escalated log levels did NOT fail (which is bad :( - re-throwing original test failure exception');
        }
        throw $initiallyFailedTestException;
    }

    /**
     * @param class-string $testClass
     */
    protected static function buildDbgDescForTest(string $testClass, string $testFunc): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc;
    }

    /**
     * @param class-string $testClass
     */
    protected static function buildDbgDescForTestWithArgs(string $testClass, string $testFunc, MixedMap $testArgs): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc . '(' . LoggableToString::convert($testArgs) . ')';
    }

    /**
     * @param TestCaseHandle $testCaseHandle
     *
     * @return array<string, LogLevel>
     */
    private function getCurrentLogLevels(TestCaseHandle $testCaseHandle): array
    {
        /** @var array<string, LogLevel> $result */
        $result = [];
        $prodCodeLogLevels = $testCaseHandle->getProdCodeLogLevels();
        if (ArrayUtilForTests::isEmpty($prodCodeLogLevels)) {
            return [];
        }
        /** @var non-empty-list<LogLevel> $prodCodeLogLevels */
        $result[self::LOG_LEVEL_FOR_PROD_CODE_KEY] = LogLevel::from(min(array_map(fn(LogLevel $logLevel) => $logLevel->value, $prodCodeLogLevels)));
        $result[self::LOG_LEVEL_FOR_TEST_CODE_KEY] = AmbientContextForTests::testConfig()->logLevel;
        return $result;
    }

    /**
     * @param array<string, LogLevel> $initialLevels
     *
     * @return iterable<array<string, LogLevel>>
     */
    public static function generateEscalatedLogLevels(array $initialLevels): iterable
    {
        Assert::assertNotEmpty($initialLevels);

        /**
         * @param array<string, LogLevel> $currentLevels
         */
        $haveCurrentLevelsReachedInitial = function (array $currentLevels) use ($initialLevels): bool {
            foreach ($initialLevels as $levelTypeKey => $initialLevel) {
                /** @var LogLevel $initialLevel */
                /** @var array<string, LogLevel> $currentLevels */
                if ($initialLevel->value < $currentLevels[$levelTypeKey]->value) {
                    return false;
                }
            }
            return true;
        };

        /** @var int $minInitialLevel */
        $minInitialLevel = min(array_map(fn(LogLevel $logLevel) => $logLevel->value, $initialLevels));
        $maxDelta = 0;
        foreach ($initialLevels as $initialLevel) {
            $maxDelta = max($maxDelta, LogLevelUtil::getHighest()->value - $initialLevel->value);
        }
        foreach (RangeUtil::generateDown(LogLevelUtil::getHighest()->value, $minInitialLevel) as $baseLevelAsInt) {
            Assert::assertGreaterThan(LogLevel::off->value, $baseLevelAsInt);
            /** @var array<string, LogLevel> $currentLevels */
            $currentLevels = [];
            foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                $currentLevels[$levelTypeKey] = LogLevel::from($baseLevelAsInt);
            }
            yield $currentLevels;

            foreach (RangeUtil::generate(1, $maxDelta + 1) as $delta) {
                foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                    if ($baseLevelAsInt < $initialLevels[$levelTypeKey]->value + $delta) {
                        continue;
                    }
                    $currentLevels[$levelTypeKey] = LogLevel::from($baseLevelAsInt - $delta);
                    if (!$haveCurrentLevelsReachedInitial($currentLevels)) {
                        yield $currentLevels;
                    }
                    $currentLevels[$levelTypeKey] = LogLevel::from($baseLevelAsInt);
                }
            }
        }
    }

    /**
     * @param array<string, LogLevel> $initialLevels
     *
     * @return iterable<array<string, LogLevel>>
     */
    protected static function generateLevelsForRunAndEscalateLogLevelOnFailure(array $initialLevels, int $eachLevelsSetMaxCount): iterable
    {
        $result = self::generateEscalatedLogLevels($initialLevels);
        $result = IterableUtil::concat($result, [$initialLevels]);
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $result = IterableUtil::duplicateEachElement($result, $eachLevelsSetMaxCount);
        return $result;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $array
     * @param TKey                $key
     *
     * @return TValue
     */
    public static function &assertAndGetFromArrayByKey(array $array, int|string $key)
    {
        Assert::assertArrayHasKey($key, $array);
        return $array[$key];
    }

    protected static function getTracerFromAppCode(): TracerInterface
    {
        return Globals::tracerProvider()->getTracer('co.elastic.edot.php.ComponentTests', null, Version::VERSION_1_25_0->url());
    }

    protected static function buildProdConfigFromAppCode(): ConfigSnapshotForProd
    {
        /** @var ?array<string, string[]> $envVarPrefixToOptNames */
        static $envVarPrefixToOptNames = null;

        $allOptsMeta = OptionsForProdMetadata::get();

        if ($envVarPrefixToOptNames === null) {
            $envVarPrefixToOptNames = [];
            foreach (OptionForProdName::cases() as $optName) {
                $envVarNamePrefix = $optName->getEnvVarNamePrefix();
                if (!array_key_exists($envVarNamePrefix, $envVarPrefixToOptNames)) {
                    $envVarPrefixToOptNames[$envVarNamePrefix] = [];
                }
                $envVarPrefixToOptNames[$envVarNamePrefix][] = $optName->name;
            }
        }

        $envVarsSnapSources = [];
        foreach ($envVarPrefixToOptNames as $currentEnvVarsPrefix => $optNames) {
            $envVarsSnapSources[] = new EnvVarsRawSnapshotSource($currentEnvVarsPrefix, $optNames);
        }
        $rawSnapshotSource = new CompositeRawSnapshotSource($envVarsSnapSources);

        $parser = new ConfigParser(AmbientContextForTests::loggerFactory());
        $rawSnapshot = $rawSnapshotSource->currentSnapshot($allOptsMeta);
        return new ConfigSnapshotForProd($parser->parse($allOptsMeta, $rawSnapshot));
    }

    protected static function getExpectedTransactionSpanName(): string
    {
        return self::isMainAppCodeHostHttp()
            ? HttpAppCodeRequestParams::DEFAULT_HTTP_REQUEST_METHOD . ' ' . HttpAppCodeRequestParams::DEFAULT_HTTP_REQUEST_URL_PATH
            : CliScriptAppCodeHostHandle::getRunScriptNameFullPath();
    }

    protected static function disableTimingDependentFeatures(AppCodeHostParams $appCodeParams): void
    {
        $appCodeParams->setProdOption(OptionForProdName::inferred_spans_enabled, false);
    }

    protected static function ensureTransactionSpanEnabled(AppCodeHostParams $appCodeParams): void
    {
        $appCodeParams->setProdOption(OptionForProdName::transaction_span_enabled, true);
        $appCodeParams->setProdOption(OptionForProdName::transaction_span_enabled_cli, true);
    }
}
