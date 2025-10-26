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
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\AttributesExpectations;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\HttpAppCodeHostHandle;
use ElasticOTelTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\ComponentTests\Util\OtlpData\SpanKind;
use ElasticOTelTests\ComponentTests\Util\SpanExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\UrlUtil;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdDefaultValues;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\MixedMap;
use OpenTelemetry\SemConv\TraceAttributes;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class TransactionSpanTest extends ComponentTestCaseBase
{
    public static function isTransactionSpanEnabled(?bool $transactionSpanEnabled, ?bool $transactionSpanEnabledCli): bool
    {
        return self::isMainAppCodeHostHttp()
            ? ($transactionSpanEnabled ?? OptionsForProdDefaultValues::TRANSACTION_SPAN_ENABLED)
            : ($transactionSpanEnabledCli ?? OptionsForProdDefaultValues::TRANSACTION_SPAN_ENABLED_CLI);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestFeatureWithVariousEnabledConfigCombos(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generateDataSets = function (): iterable {
            foreach (BoolUtil::ALL_NULLABLE_VALUES as $transactionSpanEnabled) {
                foreach (BoolUtil::ALL_NULLABLE_VALUES as $transactionSpanEnabledCli) {
                    $shouldAppCodeCreateDummySpanValues = self::isTransactionSpanEnabled($transactionSpanEnabled, $transactionSpanEnabledCli) ? BoolUtil::ALL_VALUES : [true];
                    foreach ($shouldAppCodeCreateDummySpanValues as $shouldAppCodeCreateDummySpan) {
                        yield [
                            OptionForProdName::transaction_span_enabled->name     => $transactionSpanEnabled,
                            OptionForProdName::transaction_span_enabled_cli->name => $transactionSpanEnabledCli,
                            self::SHOULD_APP_CODE_CREATE_DUMMY_SPAN_KEY           => $shouldAppCodeCreateDummySpan,
                        ];
                    }
                }
            }
        };

        return self::adaptDataSetsGeneratorToSmokeToDescToMixedMap($generateDataSets);
    }

    public static function appCodeForTestFeatureWithVariousEnabledConfigCombos(MixedMap $appCodeArgs): void
    {
        self::appCodeSetsHowFinishedAttributes(
            $appCodeArgs,
            function () use ($appCodeArgs): void {
                self::appCodeCreatesDummySpan($appCodeArgs);
            }
        );
    }

    public function implTestFeatureWithVariousEnabledConfigCombos(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $testCaseHandle = $this->getTestCaseHandle();
        $transactionSpanEnabled = $testArgs->getNullableBool(OptionForProdName::transaction_span_enabled->name);
        $transactionSpanEnabledCli = $testArgs->getNullableBool(OptionForProdName::transaction_span_enabled_cli->name);
        $isTransactionSpanEnabled = self::isTransactionSpanEnabled($transactionSpanEnabled, $transactionSpanEnabledCli);
        $shouldAppCodeCreateDummySpan = $testArgs->getBool(self::SHOULD_APP_CODE_CREATE_DUMMY_SPAN_KEY);

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($transactionSpanEnabled, $transactionSpanEnabledCli): void {
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::transaction_span_enabled, $transactionSpanEnabled);
                $appCodeParams->setProdOptionIfNotNull(OptionForProdName::transaction_span_enabled_cli, $transactionSpanEnabledCli);
            }
        );
        $appCodeHost->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestFeatureWithVariousEnabledConfigCombos']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        $expectedSpanCount = 0;
        if ($isTransactionSpanEnabled) {
            ++$expectedSpanCount;
        }
        if ($shouldAppCodeCreateDummySpan) {
            ++$expectedSpanCount;
        }
        self::assertGreaterThan(0, $expectedSpanCount);
        /** @var positive-int $expectedSpanCount */

        /** @noinspection PhpIfWithCommonPartsInspection */
        if (self::isMainAppCodeHostHttp()) {
            $expectedRootSpanKind = SpanKind::server;
            /** @var HttpAppCodeHostHandle $appCodeHost */
            $expectedRootSpanUrlParts = UrlUtil::buildUrlPartsWithDefaults(port: $appCodeHost->httpServerHandle->getMainPort());
            $rootSpanAttributesExpectations = new AttributesExpectations(
                [
                    TraceAttributes::HTTP_REQUEST_METHOD       => HttpAppCodeRequestParams::DEFAULT_HTTP_REQUEST_METHOD,
                    TraceAttributes::SERVER_ADDRESS            => $expectedRootSpanUrlParts->host,
                    TraceAttributes::SERVER_PORT               => $expectedRootSpanUrlParts->port,
                    TraceAttributes::URL_FULL                  => UrlUtil::buildFullUrl($expectedRootSpanUrlParts),
                    TraceAttributes::URL_PATH                  => $expectedRootSpanUrlParts->path,
                    TraceAttributes::URL_SCHEME                => $expectedRootSpanUrlParts->scheme,
                    self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => true,
                ]
            );
        } else {
            $expectedRootSpanKind = SpanKind::server;
            $rootSpanAttributesExpectations = new AttributesExpectations(
                attributes:           [
                                          self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => true,
                                      ],
                notAllowedAttributes: [
                                          TraceAttributes::HTTP_REQUEST_METHOD,
                                          TraceAttributes::HTTP_REQUEST_BODY_SIZE,
                                          TraceAttributes::SERVER_ADDRESS,
                                          TraceAttributes::URL_FULL,
                                          TraceAttributes::URL_PATH,
                                          TraceAttributes::URL_SCHEME,
                                          TraceAttributes::USER_AGENT_ORIGINAL,
                                      ]
            );
        }
        $expectationsForRootSpan = (new SpanExpectationsBuilder())->name(self::getExpectedTransactionSpanName())->kind($expectedRootSpanKind)->attributes($rootSpanAttributesExpectations)->build();

        $expectedDummySpanKind = SpanKind::internal;
        $expectationsForDummySpan = (new SpanExpectationsBuilder())->name(self::APP_CODE_DUMMY_SPAN_NAME)->kind($expectedDummySpanKind)->build();

        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans($expectedSpanCount));
        $dbgCtx->add(compact('agentBackendComms'));

        $rootSpan = null;
        $dummySpan = null;
        if ($isTransactionSpanEnabled) {
            $rootSpans = IterableUtil::toList($agentBackendComms->findRootSpans());
            self::assertCount(1, $rootSpans);
            /** @var Span $rootSpan */
            $rootSpan = ArrayUtilForTests::getFirstValue($rootSpans);
            if ($shouldAppCodeCreateDummySpan) {
                $childSpans = IterableUtil::toList($agentBackendComms->findChildSpans($rootSpan->id));
                self::assertCount(1, $childSpans);
                /** @var Span $dummySpan */
                $dummySpan = ArrayUtilForTests::getFirstValue($childSpans);
            }
        } else {
            $dummySpan = $agentBackendComms->singleSpan();
        }
        $dbgCtx->add(compact('rootSpan', 'dummySpan'));

        // Assert

        self::assertSame($isTransactionSpanEnabled, $rootSpan !== null);
        if ($rootSpan !== null) {
            $expectationsForRootSpan->assertMatches($rootSpan);
        }

        self::assertSame($shouldAppCodeCreateDummySpan, $dummySpan !== null);
        if ($dummySpan !== null) {
            $expectationsForDummySpan->assertMatches($dummySpan);
        }
    }


    /**
     * @dataProvider dataProviderForTestFeatureWithVariousEnabledConfigCombos
     */
    public function testFeatureWithVariousEnabledConfigCombos(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestFeatureWithVariousEnabledConfigCombos($testArgs);
            }
        );
    }
}
