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

namespace ElasticOTelTests\ComponentTests\UtilTests;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\MixedMap;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @group does_not_require_external_services
 */
final class ComponentTestsUtilComponentTest extends ComponentTestCaseBase
{
    private const INITIAL_LOG_LEVELS_KEY = 'initial_log_levels';
    private const FAIL_ON_RERUN_COUNT_KEY = 'fail_on_rerun_count';
    private const SHOULD_FAIL_KEY = 'should_fail';

    /**
     * @return iterable<array{MixedMap}>
     */
    public function dataProviderForTestRunAndEscalateLogLevelOnFailure(): iterable
    {
        $initialLogLevels = [LogLevel::info, LogLevel::trace, LogLevel::debug];

        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionOnlyFirstValueCombinable(self::LOG_LEVEL_FOR_PROD_CODE_KEY, $initialLogLevels)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::LOG_LEVEL_FOR_TEST_CODE_KEY, $initialLogLevels)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::FAIL_ON_RERUN_COUNT_KEY, [1, 2, 3])
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_FAIL_KEY)
            ->addKeyedDimensionOnlyFirstValueCombinable(OptionForTestsName::escalated_reruns_max_count->name, [2, 0])
            ->build();

        return self::adaptToSmoke(DataProviderForTestBuilder::convertEachDataSetToMixedMap($result));
    }

    private static function buildFailMessage(int $runCount): string
    {
        return 'Dummy failed; run count: ' . $runCount;
    }

    public static function appCodeForTestRunAndEscalateLogLevelOnFailure(MixedMap $appCodeArgs): void
    {
        self::appCodeSetsHowFinishedAttributes(
            $appCodeArgs,
            function () use ($appCodeArgs): void {
                DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
                try {
                    $dbgCtx->add(compact('appCodeArgs'));
                    $dbgCtx->add(['testConfig' => AmbientContextForTests::testConfig()]);
                    $expectedLogLevelForProdCode = $appCodeArgs->getLogLevel(self::LOG_LEVEL_FOR_PROD_CODE_KEY);
                    $dbgCtx->add(compact('expectedLogLevelForProdCode'));
                    $prodConfig = self::buildProdConfigFromAppCode();
                    $dbgCtx->add(compact('prodConfig'));
                    $actualLogLevelForProdCode = $prodConfig->effectiveLogLevel();
                    $dbgCtx->add(compact('actualLogLevelForProdCode'));
                    self::assertSame($expectedLogLevelForProdCode, $actualLogLevelForProdCode);
                    $expectedLogLevelForTestCode = $appCodeArgs->getLogLevel(self::LOG_LEVEL_FOR_TEST_CODE_KEY);
                    $dbgCtx->add(compact('expectedLogLevelForTestCode'));
                    $actualLogLevelForTestCode = AmbientContextForTests::testConfig()->logLevel;
                    $dbgCtx->add(compact('actualLogLevelForTestCode'));
                    self::assertSame($expectedLogLevelForTestCode, $actualLogLevelForTestCode);
                } finally {
                    $dbgCtx->pop();
                }
            }
        );
    }

    public function test0WithoutEscalation(): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestRunAndEscalateLogLevelOnFailure']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testCaseHandle): void {
                /** @var array<string, LogLevel> $appCodeArgs */
                $appCodeArgs = [
                    self::LOG_LEVEL_FOR_PROD_CODE_KEY => ArrayUtilForTests::getSingleValue($testCaseHandle->getProdCodeLogLevels()),
                    self::LOG_LEVEL_FOR_TEST_CODE_KEY => AmbientContextForTests::testConfig()->logLevel,
                ];
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
            }
        );

        $span = $this->waitForOneSpan($testCaseHandle);
        self::assertTrue($span->attributes->getBool(self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY));
    }

    /**
     * @return array<string, ?string>
     */
    private static function unsetLogLevelRelatedEnvVars(): array
    {
        $envVars = EnvVarUtilForTests::getAll();
        $logLevelRelatedEnvVarsToRestore = [];
        foreach (OptionForProdName::getAllLogLevelRelated() as $optName) {
            $envVarName = $optName->toEnvVarName();
            if (array_key_exists($envVarName, $envVars)) {
                $logLevelRelatedEnvVarsToRestore[$envVarName] = $envVars[$envVarName];
                EnvVarUtilForTests::unset($envVarName);
            } else {
                $logLevelRelatedEnvVarsToRestore[$envVarName] = null;
            }

            self::assertNull(EnvVarUtilForTests::get($envVarName));
        }
        return $logLevelRelatedEnvVarsToRestore;
    }

    /**
     * @dataProvider dataProviderForTestRunAndEscalateLogLevelOnFailure
     */
    public function testRunAndEscalateLogLevelOnFailure(MixedMap $testArgs): void
    {
        $logLevelRelatedEnvVarsToRestore = self::unsetLogLevelRelatedEnvVars();
        $prodCodeSyslogLevelEnvVarName = OptionForProdName::log_level_syslog->toEnvVarName();
        $initialLogLevelForProdCode = $testArgs->getLogLevel(self::LOG_LEVEL_FOR_PROD_CODE_KEY);
        EnvVarUtilForTests::set($prodCodeSyslogLevelEnvVarName, $initialLogLevelForProdCode->name);

        $logLevelForTestCodeToRestore = AmbientContextForTests::testConfig()->logLevel;
        $initialLogLevelForTestCode = $testArgs->getLogLevel(self::LOG_LEVEL_FOR_TEST_CODE_KEY);
        AmbientContextForTests::resetLogLevel($initialLogLevelForTestCode);

        $rerunsMaxCountToRestore = AmbientContextForTests::testConfig()->escalatedRerunsMaxCount;
        $rerunsMaxCount = $testArgs->getInt(OptionForTestsName::escalated_reruns_max_count->name);
        AmbientContextForTests::resetEscalatedRerunsMaxCount($rerunsMaxCount);

        $initialLevels = [];
        foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
            $initialLevels[$levelTypeKey] = $testArgs->getLogLevel($levelTypeKey);
        }
        $testArgs[self::INITIAL_LOG_LEVELS_KEY] = $initialLevels;
        $expectedEscalatedLevelsSeqCount = IterableUtil::count(self::generateLevelsForRunAndEscalateLogLevelOnFailure($initialLevels, $rerunsMaxCount));
        if ($rerunsMaxCount === 0) {
            self::assertSame(0, $expectedEscalatedLevelsSeqCount);
        }
        $failOnRerunCountArg = $testArgs->getInt(self::FAIL_ON_RERUN_COUNT_KEY);
        $expectedFailOnRunCount = $failOnRerunCountArg <= $expectedEscalatedLevelsSeqCount ? ($failOnRerunCountArg + 1) : 1;
        $expectedMessage = self::buildFailMessage($expectedFailOnRunCount);
        $shouldFail = $testArgs->getBool(self::SHOULD_FAIL_KEY);

        $nextRunCount = 1;
        try {
            self::runAndEscalateLogLevelOnFailure(
                self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
                function () use ($testArgs, &$nextRunCount): void {
                    $testArgs['currentRunCount'] = $nextRunCount++;
                    $this->implTestRunAndEscalateLogLevelOnFailure($testArgs);
                }
            );
            $runAndEscalateLogLevelOnFailureExitedNormally = true;
        } catch (AssertionFailedError $ex) {
            $runAndEscalateLogLevelOnFailureExitedNormally = false;
            self::assertStringContainsString($expectedMessage, $ex->getMessage());
        }
        self::assertSame(!$shouldFail, $runAndEscalateLogLevelOnFailureExitedNormally);

        self::assertSame($rerunsMaxCount, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
        AmbientContextForTests::resetEscalatedRerunsMaxCount($rerunsMaxCountToRestore);

        self::assertSame($initialLogLevelForTestCode, AmbientContextForTests::testConfig()->logLevel);
        AmbientContextForTests::resetLogLevel($logLevelForTestCodeToRestore);

        self::assertSame($initialLogLevelForProdCode->name, EnvVarUtilForTests::get($prodCodeSyslogLevelEnvVarName));
        foreach ($logLevelRelatedEnvVarsToRestore as $envVarName => $envVarValue) {
            EnvVarUtilForTests::setOrUnset($envVarName, $envVarValue);
        }
    }

    private function implTestRunAndEscalateLogLevelOnFailure(MixedMap $testArgs): void
    {
        $currentRunCount = $testArgs->getInt('currentRunCount');
        self::assertGreaterThanOrEqual(1, $currentRunCount);
        $currentReRunCount = $currentRunCount === 1 ? 0 : ($currentRunCount - 1);
        $shouldFail = $testArgs->getBool(self::SHOULD_FAIL_KEY);
        $failOnRerunCountArg = $testArgs->getInt(self::FAIL_ON_RERUN_COUNT_KEY);
        /** @var array<string, LogLevel> $initialLevels */
        $initialLevels = $testArgs->getArray(self::INITIAL_LOG_LEVELS_KEY);
        $shouldCurrentRunFail = $shouldFail && ($currentRunCount === 1 || $currentReRunCount === $failOnRerunCountArg);
        if ($currentRunCount === 1) {
            $expectedLevels = $initialLevels;
        } else {
            $rerunsMaxCount = $testArgs->getInt(OptionForTestsName::escalated_reruns_max_count->name);
            self::assertTrue(
                IterableUtil::getNthValue(
                    self::generateLevelsForRunAndEscalateLogLevelOnFailure($initialLevels, $rerunsMaxCount),
                    $currentReRunCount - 1,
                    $expectedLevels /* <- out */
                )
            );
        }
        /** @var array<string, LogLevel> $expectedLevels */

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestRunAndEscalateLogLevelOnFailure']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($expectedLevels): void {
                /** @var array<string, LogLevel> $appCodeArgs */
                $appCodeArgs = [];
                foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                    $appCodeArgs[$levelTypeKey] = $expectedLevels[$levelTypeKey];
                }
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
            }
        );

        $span = $this->waitForOneSpan($testCaseHandle);
        self::assertTrue($span->attributes->getBool(self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY));

        if ($shouldCurrentRunFail) {
            self::fail(self::buildFailMessage($currentRunCount));
        }
    }
}
