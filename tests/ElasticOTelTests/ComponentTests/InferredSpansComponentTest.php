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

use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\SpanSequenceValidator;
use ElasticOTelTests\ComponentTests\Util\WaitForEventCounts;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class InferredSpansComponentTest extends ComponentTestCaseBase
{
    private const IS_INFERRED_SPANS_ENABLED_KEY = 'IS_INFERRED_SPANS_ENABLED';
    private const CAPTURE_SLEEPS_KEY = 'CAPTURE_SLEEPS';

    private const SLEEP_DURATION_SECONDS = 5;
    private const INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS = self::SLEEP_DURATION_SECONDS - 2;
    private const INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS = self::SLEEP_DURATION_SECONDS * 3 - 1;

    private const SLEEP_FUNC_NAME = 'sleep';
    private const USLEEP_FUNC_NAME = 'usleep';
    private const TIME_NANOSLEEP_FUNC_NAME = 'time_nanosleep';
    private const SLEEP_FUNC_NAMES = [self::SLEEP_FUNC_NAME, self::USLEEP_FUNC_NAME, self::TIME_NANOSLEEP_FUNC_NAME];

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

    private static function usleep(int $secondsToSleep): void
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
     * @param int                              $secondsToSleep
     * @param string                           $sleepFuncToUse
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function mySleep(int $secondsToSleep, string $sleepFuncToUse): void
    {
        switch ($sleepFuncToUse) {
            case self::SLEEP_FUNC_NAME:
                self::assertSame(0, sleep($secondsToSleep));
                break;
            case self::USLEEP_FUNC_NAME:
                self::usleep($secondsToSleep);
                break;
            case self::TIME_NANOSLEEP_FUNC_NAME:
                self::assertTrue(time_nanosleep($secondsToSleep, nanoseconds: 0));
                break;
            default:
                self::fail('Unknown sleepFuncToUse: `' . $sleepFuncToUse . '\'');
        }
    }

    public static function appCodeForTestInferredSpans(): void
    {
        self::mySleep(self::SLEEP_DURATION_SECONDS, self::SLEEP_FUNC_NAME);
        self::mySleep(self::SLEEP_DURATION_SECONDS, self::USLEEP_FUNC_NAME);
        self::mySleep(self::SLEEP_DURATION_SECONDS, self::TIME_NANOSLEEP_FUNC_NAME);
    }

    private function implTestInferredSpans(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $isInferredSpansEnabled = $testArgs->getBool(self::IS_INFERRED_SPANS_ENABLED_KEY);
        $shouldCaptureSleeps = $testArgs->getBool(self::CAPTURE_SLEEPS_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($isInferredSpansEnabled, $shouldCaptureSleeps): void {
                $appCodeParams->setProdOption(OptionForProdName::inferred_spans_enabled, $isInferredSpansEnabled);
                $inferredMinDuration = $shouldCaptureSleeps ? self::INFERRED_MIN_DURATION_SECONDS_TO_CAPTURE_SLEEPS : self::INFERRED_MIN_DURATION_SECONDS_TO_OMIT_SLEEPS;
                $appCodeParams->setProdOption(OptionForProdName::inferred_spans_min_duration, $inferredMinDuration . 's');
            }
        );
        $appCodeHost->execAppCode(AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestInferredSpans']));

        // Total number of spans is 3 sleep spans + 1 span for appCode method + 1 local root span
        $expectedSpanCount = ($shouldCaptureSleeps ? 3 : 0) + 1 + 1;
        $exportedData = $testCaseHandle->waitForEnoughExportedData(WaitForEventCounts::spans($expectedSpanCount));
        $dbgCtx->add(compact('exportedData'));

        $spanExpectationsBuilder = new InferredSpanExpectationsBuilder();

        $appCodeSpanExpectations = $expectationsBuilder->fromClassMethodNamesAndStackTrace(
            ClassNameUtil::fqToShort(__CLASS__),
            $appCodeMethod,
            true /* <- isStatic */,
            $stackTraces[self::APP_CODE_SPAN_STACK_TRACE],
            true /* <- allowExpectedStackTraceToBePrefix */
        );
        $appCodeSpan = $dataFromAgent->singleSpanByName($appCodeSpanExpectations->name->getValue());
        self::assertSame($tx->id, $appCodeSpan->parentId, $ctxStr);
        $appCodeSpan->assertMatches($appCodeSpanExpectations);

        if (!$shouldCaptureSleeps) {
            return;
        }

        $expectedSleepSpans = [];
        $actualSleepSpans = [];
        foreach (self::SLEEP_FUNC_NAMES as $sleepFunc) {
            $stackTrace = $stackTraces[$sleepFunc];
            $expectedSleepSpans[] = $expectationsBuilder->fromFuncNameAndStackTrace(
                $sleepFunc,
                $stackTrace,
                true /* <- allowExpectedStackTraceToBePrefix */
            );
            $sleepSpan = $dataFromAgent->singleSpanByName($sleepFunc);
            $actualSleepSpans[] = $sleepSpan;
            self::assertSame($appCodeSpan->id, $sleepSpan->parentId);
        }

        SpanSequenceValidator::assertSequenceAsExpected($expectedSleepSpans, $actualSleepSpans);
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
