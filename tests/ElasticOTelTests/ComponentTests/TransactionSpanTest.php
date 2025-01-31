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
use ElasticOTelTests\ComponentTests\Util\HttpAppCodeHostHandle;
use ElasticOTelTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\Span;
use ElasticOTelTests\ComponentTests\Util\SpanAttributesExpectations;
use ElasticOTelTests\ComponentTests\Util\SpanExpectations;
use ElasticOTelTests\ComponentTests\Util\SpanKind;
use ElasticOTelTests\ComponentTests\Util\UrlUtil;
use ElasticOTelTests\ComponentTests\Util\WaitForEventCounts;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdDefaultValues;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpSchemes;
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
    public function dataProviderForTestEnabledConfig(): iterable
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

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($generateDataSets);
    }

    public static function appCodeForTestEnabledConfig(MixedMap $appCodeArgs): void
    {
        self::appCodeSetsHowFinishedAttributes(
            $appCodeArgs,
            function () use ($appCodeArgs): void {
                self::appCodeCreatesDummySpan($appCodeArgs);
            }
        );
    }

    public function implTestEnabledConfig(MixedMap $testArgs): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

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
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestEnabledConfig']),
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
            $rootSpanAttributesExpectations = new SpanAttributesExpectations(
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
            $rootSpanAttributesExpectations = new SpanAttributesExpectations(
                [
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have http.request.method attribute?
                    TraceAttributes::HTTP_REQUEST_METHOD       => HttpMethods::GET,
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have http.request.body.size attribute?
                    TraceAttributes::HTTP_REQUEST_BODY_SIZE    => '',
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have server.address attribute?
                    TraceAttributes::SERVER_ADDRESS            => 'localhost',
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have url.full attribute?
                    TraceAttributes::URL_FULL                  => 'http://localhost',
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have url.path attribute?
                    TraceAttributes::URL_PATH                  => '',
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have url.scheme attribute?
                    TraceAttributes::URL_SCHEME                => HttpSchemes::HTTP,
                    // TODO: Sergey Kleyman: Should transaction span for CLI script have user_agent.original attribute?
                    TraceAttributes::USER_AGENT_ORIGINAL       => '',
                    self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => true,
                ]
            );
        }
        $expectationsForRootSpan = new SpanExpectations(self::getExpectedTransactionSpanName(), $expectedRootSpanKind, $rootSpanAttributesExpectations);

        $expectedDummySpanKind = SpanKind::internal;
        $expectationsForDummySpan = new SpanExpectations(self::APP_CODE_DUMMY_SPAN_NAME, $expectedDummySpanKind);

        $exportedData = $testCaseHandle->waitForEnoughExportedData(WaitForEventCounts::spans($expectedSpanCount));
        $dbgCtx->add(compact('exportedData'));

        $rootSpan = null;
        $dummySpan = null;
        if ($isTransactionSpanEnabled) {
            $rootSpans = IterableUtil::toList($exportedData->findRootSpans());
            self::assertCount(1, $rootSpans);
            /** @var Span $rootSpan */
            $rootSpan = ArrayUtilForTests::getFirstValue($rootSpans);
            if ($shouldAppCodeCreateDummySpan) {
                $childSpans = IterableUtil::toList($exportedData->findChildSpans($rootSpan->id));
                self::assertCount(1, $childSpans);
                /** @var Span $dummySpan */
                $dummySpan = ArrayUtilForTests::getFirstValue($childSpans);
            }
        } else {
            $dummySpan = $exportedData->singleSpan();
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

        $dbgCtx->pop();
    }


    /**
     * @dataProvider dataProviderForTestEnabledConfig
     */
    public function testEnabledConfig(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestEnabledConfig($testArgs);
            }
        );
    }
}
