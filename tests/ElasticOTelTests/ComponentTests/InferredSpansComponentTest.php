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

use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\InferredSpanExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\OTelUtil;
use ElasticOTelTests\ComponentTests\Util\PhpSerializationUtil;
use ElasticOTelTests\ComponentTests\Util\SpanSequenceExpectations;
use ElasticOTelTests\ComponentTests\Util\StackTraceExpectations;
use ElasticOTelTests\ComponentTests\Util\TestCaseHandle;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\StackTraceUtil;
use OpenTelemetry\SemConv\TraceAttributes;

use function debug_backtrace;

/**
 * @group smoke
 * @group does_not_require_external_services
 *
 * @phpstan-import-type DebugBacktraceResult from StackTraceUtil
 * @phpstan-type FuncName string
 * @phpstan-type ExpectedHelperDataForFunc array{'stack_trace': DebugBacktraceResult, 'line_number'?: int}
 * @phpstan-type ExpectedHelperData array<FuncName, ExpectedHelperDataForFunc>
 */
final class InferredSpansComponentTest extends ComponentTestCaseBase
{
    private const IS_INFERRED_SPANS_ENABLED_KEY = 'is_inferred_spans_enabled';
    private const CAPTURE_SLEEPS_KEY = 'capture_sleeps';

    private const SLEEP_DURATION_SECONDS = 5;
    private const INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS = self::SLEEP_DURATION_SECONDS - 2;
    private const INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS = self::SLEEP_DURATION_SECONDS * 3 - 1;

    private const SLEEP_FUNC_NAME = 'sleep';
    private const MULTI_STEP_USLEEP_FUNC_NAME = 'multiStepUsleep';
    private const TIME_NANOSLEEP_FUNC_NAME = 'time_nanosleep';
    private const SLEEP_FUNC_NAMES = [self::SLEEP_FUNC_NAME, self::MULTI_STEP_USLEEP_FUNC_NAME, self::TIME_NANOSLEEP_FUNC_NAME];

    private const IS_CURRENT_RUN_TO_GET_EXPECTED_HELPER_DATA_KEY = 'is_current_run_to_get_expected_helper_data';
    private const EXPECTED_HELPER_DATA_KEY = 'expected_helper_data';
    private const STACK_TRACE_KEY = 'stack_trace';
    private const LINE_NUMBER_KEY = 'line_number';

    /**
     * @return iterable<array{MixedMap}>
     */
    public static function dataProviderForTestInferredSpans(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::IS_INFERRED_SPANS_ENABLED_KEY)
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::CAPTURE_SLEEPS_KEY)
            ->build();

        return self::adaptToSmoke(DataProviderForTestBuilder::convertEachDataSetToMixedMap($result));
    }

    private static function multiStepUsleep(int $secondsToSleep): void
    {
        $microsecondsInSecond = 1000 * 1000;
        $microsecondsInEachSleep = $microsecondsInSecond / 5;
        $numberOfSleeps = intval(($secondsToSleep * $microsecondsInSecond) / $microsecondsInEachSleep);
        $lastSleep = $secondsToSleep % $microsecondsInEachSleep;
        for ($i = 0; $i < $numberOfSleeps; ++$i) {
            usleep($microsecondsInEachSleep);
        }
        usleep($lastSleep);
    }

    /**
     * @phpstan-param ExpectedHelperData $expectedHelperData
     */
    private static function mySleep(string $sleepFuncToUse, bool $isCurrentRunToGetExpectedHelperData, /* ref */ array &$expectedHelperData): void
    {
        $secondsToSleep = $isCurrentRunToGetExpectedHelperData ? 0 : self::SLEEP_DURATION_SECONDS;
        switch ($sleepFuncToUse) {
            case self::SLEEP_FUNC_NAME:
                self::assertSame(0, sleep($secondsToSleep));
                $sleepCallLine = __LINE__ - 1;
                break;
            case self::MULTI_STEP_USLEEP_FUNC_NAME:
                self::multiStepUsleep($secondsToSleep);
                $sleepCallLine = __LINE__ - 1;
                break;
            case self::TIME_NANOSLEEP_FUNC_NAME:
                self::assertTrue(time_nanosleep($secondsToSleep, nanoseconds: 0));
                $sleepCallLine = __LINE__ - 1;
                break;
            default:
                self::fail('Unknown sleepFuncToUse: `' . $sleepFuncToUse . '\'');
        }

        if ($isCurrentRunToGetExpectedHelperData) {
            $expectedStackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $expectedHelperData[$sleepFuncToUse] = [self::STACK_TRACE_KEY => $expectedStackTrace, self::LINE_NUMBER_KEY => $sleepCallLine];
        }
    }

    public static function appCodeForTestInferredSpans(MixedMap $appCodeArgs): void
    {
        $isCurrentRunToGetExpectedHelperData = $appCodeArgs->getBool(self::IS_CURRENT_RUN_TO_GET_EXPECTED_HELPER_DATA_KEY);

        /** @var ExpectedHelperData $expectedHelperData */
        $expectedHelperData = [];

        self::mySleep(self::SLEEP_FUNC_NAME, $isCurrentRunToGetExpectedHelperData, /* ref */ $expectedHelperData);
        self::mySleep(self::MULTI_STEP_USLEEP_FUNC_NAME, $isCurrentRunToGetExpectedHelperData, /* ref */ $expectedHelperData);
        self::mySleep(self::TIME_NANOSLEEP_FUNC_NAME, $isCurrentRunToGetExpectedHelperData, /* ref */ $expectedHelperData);

        if ($isCurrentRunToGetExpectedHelperData) {
            // Slice 1 frame for this function call since this function call is converted to an inferred span
            // and properties from the stack frame converted to an inferred span go to CODE_FILE_PATH and CODE_LINE_NUMBER attributes.
            // This method is a special case since it's called by call_user_func, so there should not be CODE_FILE_PATH and CODE_LINE_NUMBER attributes.
            $expectedHelperData[__FUNCTION__] = [self::STACK_TRACE_KEY => array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), offset: 1)];
            OTelUtil::addActiveSpanAttributes([self::EXPECTED_HELPER_DATA_KEY => PhpSerializationUtil::serializeToString($expectedHelperData)]);
        }
    }

    private function implTestInferredSpans(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $isInferredSpansEnabled = $testArgs->getBool(self::IS_INFERRED_SPANS_ENABLED_KEY);
        $shouldCaptureSleeps = $testArgs->getBool(self::CAPTURE_SLEEPS_KEY);

        $appCodeTarget = AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestInferredSpans']);
        $appCodeMethodName = $appCodeTarget->appCodeMethod;
        self::assertIsString($appCodeMethodName);

        $setupAndCallAppCode = function (bool $isCurrentRunToGetExpectedHelperData) use ($isInferredSpansEnabled, $shouldCaptureSleeps, $appCodeTarget): TestCaseHandle {
            $testCaseHandle = $this->getTestCaseHandle();
            $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
                function (AppCodeHostParams $appCodeParams) use ($isInferredSpansEnabled, $shouldCaptureSleeps, $isCurrentRunToGetExpectedHelperData): void {
                    $appCodeParams->setProdOption(OptionForProdName::inferred_spans_enabled, (!$isCurrentRunToGetExpectedHelperData) && $isInferredSpansEnabled);
                    $inferredMinDuration = $shouldCaptureSleeps ? self::INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS : self::INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS;
                    $appCodeParams->setProdOption(OptionForProdName::inferred_spans_min_duration, $inferredMinDuration . 's');
                }
            );
            $appCodeHost->execAppCode(
                $appCodeTarget,
                function (AppCodeRequestParams $appCodeReqParams) use ($isCurrentRunToGetExpectedHelperData): void {
                    $appCodeReqParams->setAppCodeArgs([self::IS_CURRENT_RUN_TO_GET_EXPECTED_HELPER_DATA_KEY => $isCurrentRunToGetExpectedHelperData]);
                }
            );

            return $testCaseHandle;
        };

        /**
         * @return ExpectedHelperData
         */
        $doDummyRunToGetExpectedHelperData = function () use ($setupAndCallAppCode, $dbgCtx): array {
            $testCaseHandle = $setupAndCallAppCode(isCurrentRunToGetExpectedHelperData: true);
            // For the dummy run to get expected helper data inferred spans feature is disabled so only a local root span should be created
            $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans(1));
            $dbgCtx->add(compact('agentBackendComms'));
            $this->tearDownTestCaseHandle();
            $testCaseHandle = null;
            $rootSpan = IterableUtil::singleValue($agentBackendComms->findRootSpans());
            $expectedHelperData = PhpSerializationUtil::unserializeFromString($rootSpan->attributes->getString(self::EXPECTED_HELPER_DATA_KEY));
            $dbgCtx->add(compact('expectedHelperData'));
            self::assertIsArray($expectedHelperData);
            /** @var ExpectedHelperData $expectedHelperData */
            return $expectedHelperData;
        };

        $expectedHelperData = $doDummyRunToGetExpectedHelperData();
        $testCaseHandle = $setupAndCallAppCode(isCurrentRunToGetExpectedHelperData: false);

        // Inferred spans count is at least 4: 3 sleep spans + 1 span for appCode method
        $expectedInferredSpansMinCount = $isInferredSpansEnabled ? (($shouldCaptureSleeps ? 3 : 0) + 1) : 0;
        // Regular (i.e., not inferred) spans count is 1 - the automatic root span
        $expectedSpanMinCount = $expectedInferredSpansMinCount + 1;
        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(
            $isInferredSpansEnabled ? WaitForOTelSignalCounts::spansAtLeast($expectedSpanMinCount) : WaitForOTelSignalCounts::spans($expectedSpanMinCount)
        );
        $dbgCtx->add(compact('agentBackendComms'));

        $rootSpan = IterableUtil::singleValue($agentBackendComms->findRootSpans());
        foreach ($agentBackendComms->spans() as $span) {
            self::assertSame($rootSpan->traceId, $span->traceId);
        }

        if (!$isInferredSpansEnabled) {
            return;
        }

        $stackTraceExpectations = StackTraceExpectations::fromDebugBacktrace($expectedHelperData[$appCodeMethodName][self::STACK_TRACE_KEY]);
        $appCodeSpanExpectations = (new InferredSpanExpectationsBuilder())
            ->addNotAllowedAttribute(TraceAttributes::CODE_FILE_PATH) // appCodeForTestInferredSpans method is called by call_user_func so there is no CODE_FILE_PATH
            ->addNotAllowedAttribute(TraceAttributes::CODE_LINE_NUMBER) // appCodeForTestInferredSpans method is called by call_user_func so there is no CODE_LINE_NUMBER
            ->buildForStaticMethod(__CLASS__, $appCodeMethodName, $stackTraceExpectations);
        $appCodeSpan = $agentBackendComms->singleSpanByName($appCodeSpanExpectations->name->expectedValue->getValue());
        self::assertTrue($agentBackendComms->isSpanDescendantOf($appCodeSpan, $rootSpan));
        $appCodeSpanExpectations->assertMatches($appCodeSpan);

        if (!$shouldCaptureSleeps) {
            return;
        }

        $sleepSpansExpectationsBuilder = (new InferredSpanExpectationsBuilder())->addAttribute(TraceAttributes::CODE_FILE_PATH, __FILE__);
        $expectedSleepSpans = [];
        $actualSleepSpans = [];
        foreach (self::SLEEP_FUNC_NAMES as $sleepFunc) {
            $expectedCodeLineNumber = AssertEx::isPositiveInt(ArrayUtil::getValueIfKeyExistsElse(self::LINE_NUMBER_KEY, $expectedHelperData[$sleepFunc], null));
            $stackTraceExpectations = StackTraceExpectations::fromDebugBacktrace($expectedHelperData[$sleepFunc][self::STACK_TRACE_KEY]);
            $expectedSleepSpan = $sleepFunc === self::MULTI_STEP_USLEEP_FUNC_NAME
                ? $sleepSpansExpectationsBuilder->buildForStaticMethod(__CLASS__, $sleepFunc, $stackTraceExpectations, $expectedCodeLineNumber)
                : $sleepSpansExpectationsBuilder->buildForFunction($sleepFunc, $stackTraceExpectations, $expectedCodeLineNumber);
            $expectedSleepSpans[] = $expectedSleepSpan;
            $actualSleepSpan = $agentBackendComms->singleSpanByName($expectedSleepSpan->name->expectedValue->getValue());
            $actualSleepSpans[] = $actualSleepSpan;
            self::assertTrue($agentBackendComms->isSpanDescendantOf($actualSleepSpan, $appCodeSpan));
        }

        (new SpanSequenceExpectations($expectedSleepSpans))->assertMatches($actualSleepSpans);
    }

    /**
     * @dataProvider dataProviderForTestInferredSpans
     */
    public function testInferredSpans(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestInferredSpans($testArgs);
            }
        );
    }
}
