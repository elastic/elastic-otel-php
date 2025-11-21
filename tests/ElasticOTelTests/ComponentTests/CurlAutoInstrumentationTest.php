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

use CurlHandle;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\AttributesExpectations;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\CurlHandleForTests;
use ElasticOTelTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\HttpClientUtilForTests;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\ComponentTests\Util\OtlpData\SpanKind;
use ElasticOTelTests\ComponentTests\Util\PhpSerializationUtil;
use ElasticOTelTests\ComponentTests\Util\RequestHeadersRawSnapshotSource;
use ElasticOTelTests\ComponentTests\Util\ResourcesClient;
use ElasticOTelTests\ComponentTests\Util\SpanExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\UrlUtil;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\GlobalUnderscoreServer;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\RangeUtil;
use OpenTelemetry\Contrib\Instrumentation\Curl\CurlInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class CurlAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const AUTO_INSTRUMENTATION_NAME = 'curl';

    private const RESOURCES_CLIENT_KEY = 'resources_client';
    private const HTTP_APP_CODE_REQUEST_PARAMS_FOR_SERVER_KEY = 'http_app_code_request_params_for_server';
    private const HTTP_REQUEST_HEADER_NAME_PREFIX = 'Elastic_OTel_PHP_custom_header_';
    private const SERVER_RESPONSE_BODY = 'Response from server app code body';
    private const SERVER_RESPONSE_HTTP_STATUS = 234;

    private const ENABLE_CURL_INSTRUMENTATION_FOR_CLIENT_KEY = 'enable_curl_instrumentation_for_client';
    private const ENABLE_CURL_INSTRUMENTATION_FOR_SERVER_KEY = 'enable_curl_instrumentation_for_server';

    /**
     * @param iterable<int> $suffixes
     *
     * @return array<string, string>
     */
    private static function genHeaders(iterable $suffixes): array
    {
        $result = [];
        foreach ($suffixes as $suffix) {
            $headerName = self::HTTP_REQUEST_HEADER_NAME_PREFIX . $suffix;
            $result[$headerName] = 'Value_for_' . $headerName;
        }
        return $result;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private static function convertHeadersToCurlFormat(array $headers): array
    {
        $result = [];
        foreach ($headers as $headerName => $headerValue) {
            $result[] = $headerName . ': ' . $headerValue;
        }
        return $result;
    }

    public static function appCodeServer(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $dbgCtx->add(['$_SERVER' => IterableUtil::toMap(GlobalUnderscoreServer::getAll())]);

        $dbgCtx->add(['php_sapi_name()' => php_sapi_name()]);
        self::assertNotEquals('cli', php_sapi_name());

        self::assertSame(HttpMethods::GET, GlobalUnderscoreServer::requestMethod());

        $expectedHeaders = self::genHeaders(RangeUtil::generateFromToIncluding(2, 3));
        foreach ($expectedHeaders as $expectedHeaderName => $expectedHeaderValue) {
            $dbgCtx->add(compact('expectedHeaderName', 'expectedHeaderValue'));
            self::assertSame($expectedHeaderValue, GlobalUnderscoreServer::getRequestHeaderValue($expectedHeaderName));
        }

        http_response_code(self::SERVER_RESPONSE_HTTP_STATUS);
        echo self::SERVER_RESPONSE_BODY;
    }

    public static function appCodeClient(MixedMap $appCodeArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertTrue(extension_loaded('curl'));

        $enableCurlInstrumentationForClient = $appCodeArgs->getBool(self::ENABLE_CURL_INSTRUMENTATION_FOR_CLIENT_KEY);
        if ($enableCurlInstrumentationForClient) {
            self::assertTrue(class_exists(CurlInstrumentation::class, autoload: false));
            AssertEx::sameConstValues(CurlInstrumentation::NAME, self::AUTO_INSTRUMENTATION_NAME);
        }

        $requestParams = $appCodeArgs->getObject(self::HTTP_APP_CODE_REQUEST_PARAMS_FOR_SERVER_KEY, HttpAppCodeRequestParams::class);
        $resourcesClient = $appCodeArgs->getObject(self::RESOURCES_CLIENT_KEY, ResourcesClient::class);

        $curlHandleRaw = curl_init(UrlUtil::buildFullUrl($requestParams->urlParts));
        self::assertInstanceOf(CurlHandle::class, $curlHandleRaw);
        $curlHandle = new CurlHandleForTests($curlHandleRaw, $resourcesClient);

        self::assertTrue($curlHandle->setOpt(CURLOPT_CONNECTTIMEOUT, HttpClientUtilForTests::CONNECT_TIMEOUT_SECONDS));
        self::assertTrue($curlHandle->setOpt(CURLOPT_TIMEOUT, HttpClientUtilForTests::TIMEOUT_SECONDS));

        $dataPerRequestHeaderName = RequestHeadersRawSnapshotSource::optionNameToHeaderName(OptionForTestsName::data_per_request->name);
        $dataPerRequestHeaderValue = PhpSerializationUtil::serializeToString($requestParams->dataPerRequest);

        $notFinalHeaders12 = self::genHeaders([1, 2]);
        $notFinalHeader2Key = array_key_last($notFinalHeaders12);
        $notFinalHeaders12[$notFinalHeader2Key] .= '_NOT_FINAL_VALUE';
        self::assertTrue($curlHandle->setOptArray([CURLOPT_HTTPHEADER => self::convertHeadersToCurlFormat($notFinalHeaders12), CURLOPT_POST => true]));

        $headers = array_merge([$dataPerRequestHeaderName => $dataPerRequestHeaderValue], self::genHeaders([2, 3]));
        self::assertTrue($curlHandle->setOptArray([CURLOPT_HTTPHEADER => self::convertHeadersToCurlFormat($headers), CURLOPT_HTTPGET => true, CURLOPT_RETURNTRANSFER => true]));

        $execRetVal = $curlHandle->exec();
        $dbgCtx->add(compact('execRetVal'));
        if ($execRetVal === false) {
            self::fail(LoggableToString::convert(['error' => $curlHandle->error(), 'errno' => $curlHandle->errno(), 'verbose output' => $curlHandle->lastVerboseOutput()]));
        }
        $dbgCtx->add(['getInfo()' => $curlHandle->getInfo()]);

        self::assertSame(self::SERVER_RESPONSE_HTTP_STATUS, $curlHandle->getResponseStatusCode());
        self::assertSame(self::SERVER_RESPONSE_BODY, $execRetVal);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestLocalClientServer(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addBoolKeyedDimensionAllValuesCombinable(self::ENABLE_CURL_INSTRUMENTATION_FOR_CLIENT_KEY)
                ->addBoolKeyedDimensionAllValuesCombinable(self::ENABLE_CURL_INSTRUMENTATION_FOR_SERVER_KEY)
        );
    }

    public function implTestLocalClientServer(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $testCaseHandle = $this->getTestCaseHandle();

        $enableCurlInstrumentationForServer = $testArgs->getBool(self::ENABLE_CURL_INSTRUMENTATION_FOR_SERVER_KEY);
        $serverAppCode = $testCaseHandle->ensureAdditionalHttpAppCodeHost(
            dbgInstanceName: 'server for cUrl request',
            setParamsFunc: function (AppCodeHostParams $appCodeParams) use ($enableCurlInstrumentationForServer): void {
                self::disableTimingDependentFeatures($appCodeParams);
                if (!$enableCurlInstrumentationForServer) {
                    $appCodeParams->setProdOptionIfNotNull(OptionForProdName::disabled_instrumentations, self::AUTO_INSTRUMENTATION_NAME);
                }
            }
        );
        $appCodeRequestParamsForServer = $serverAppCode->buildRequestParams(AppCodeTarget::asRouted([__CLASS__, 'appCodeServer']));

        $enableCurlInstrumentationForClient = $testArgs->getBool(self::ENABLE_CURL_INSTRUMENTATION_FOR_CLIENT_KEY);
        $clientAppCode = $testCaseHandle->ensureMainAppCodeHost(
            setParamsFunc: function (AppCodeHostParams $appCodeParams) use ($enableCurlInstrumentationForClient): void {
                self::disableTimingDependentFeatures($appCodeParams);
                if (!$enableCurlInstrumentationForClient) {
                    $appCodeParams->setProdOptionIfNotNull(OptionForProdName::disabled_instrumentations, self::AUTO_INSTRUMENTATION_NAME);
                }
            },
            dbgInstanceName: 'client for cUrl request',
        );
        $resourcesClient = $testCaseHandle->getResourcesClient();

        $clientAppCode->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeClient']),
            function (AppCodeRequestParams $clientAppCodeReqParams) use ($testArgs, $appCodeRequestParamsForServer, $resourcesClient): void {
                $clientAppCodeReqParams->setAppCodeArgs(
                    [
                        self::HTTP_APP_CODE_REQUEST_PARAMS_FOR_SERVER_KEY => $appCodeRequestParamsForServer,
                        self::RESOURCES_CLIENT_KEY                        => $resourcesClient,
                    ]
                    + $testArgs->cloneAsArray()
                );
            }
        );

        //
        // spans: <client app code transaction span> -> <curl client span> -> <server app code transaction span>
        //        |------------------------------------------------------|    |--------------------------------|
        //        client app host                                             server app host

        $curlClientSpanAttributesExpectations = new AttributesExpectations(
            [
                TraceAttributes::CODE_FUNCTION_NAME        => 'curl_exec',
                TraceAttributes::HTTP_REQUEST_METHOD       => HttpMethods::GET,
                TraceAttributes::HTTP_RESPONSE_STATUS_CODE => self::SERVER_RESPONSE_HTTP_STATUS,
                TraceAttributes::SERVER_ADDRESS            => $appCodeRequestParamsForServer->urlParts->host,
                TraceAttributes::SERVER_PORT               => $appCodeRequestParamsForServer->urlParts->port,
                TraceAttributes::URL_FULL                  => UrlUtil::buildFullUrl($appCodeRequestParamsForServer->urlParts),
                TraceAttributes::URL_SCHEME                => $appCodeRequestParamsForServer->urlParts->scheme,
            ]
        );
        $expectationsForCurlClientSpan = (new SpanExpectationsBuilder())->name(HttpMethods::GET)->kind(SpanKind::client)->attributes($curlClientSpanAttributesExpectations)->build();

        $serverTxSpanAttributesExpectations = new AttributesExpectations(
            [
                TraceAttributes::HTTP_REQUEST_METHOD       => HttpMethods::GET,
                TraceAttributes::HTTP_RESPONSE_STATUS_CODE => self::SERVER_RESPONSE_HTTP_STATUS,
                TraceAttributes::SERVER_ADDRESS            => $appCodeRequestParamsForServer->urlParts->host,
                TraceAttributes::SERVER_PORT               => $appCodeRequestParamsForServer->urlParts->port,
                TraceAttributes::URL_FULL                  => UrlUtil::buildFullUrl($appCodeRequestParamsForServer->urlParts),
                TraceAttributes::URL_PATH                  => $appCodeRequestParamsForServer->urlParts->path,
                TraceAttributes::URL_SCHEME                => $appCodeRequestParamsForServer->urlParts->scheme,
            ]
        );
        $expectedServerTxSpanName = HttpMethods::GET . ' ' . $appCodeRequestParamsForServer->urlParts->path;
        $expectationsForServerTxSpan = (new SpanExpectationsBuilder())->name($expectedServerTxSpanName)->kind(SpanKind::server)->attributes($serverTxSpanAttributesExpectations)->build();

        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans($enableCurlInstrumentationForClient ? 3 : 2));
        $dbgCtx->add(compact('agentBackendComms'));

        //
        // Assert
        //

        if ($enableCurlInstrumentationForClient) {
            $rootSpan = $agentBackendComms->singleRootSpan();
            foreach ($agentBackendComms->spans() as $span) {
                self::assertSame($rootSpan->traceId, $span->traceId);
            }
            $curlClientSpan = $agentBackendComms->singleChildSpan($rootSpan->id);
            $expectationsForCurlClientSpan->assertMatches($curlClientSpan);
            $serverTxSpan = $agentBackendComms->singleChildSpan($curlClientSpan->id);
        } else {
            $serverTxSpan = IterableUtil::singleValue($agentBackendComms->findSpansWithAttributeValue(TraceAttributes::SERVER_PORT, $appCodeRequestParamsForServer->urlParts->port));
            self::assertNull($serverTxSpan->parentId);
            $clientTxSpan = IterableUtil::singleValue(IterableUtil::findByPredicateOnValue($agentBackendComms->spans(), fn(Span $span) => $span->parentId === null && $span !== $serverTxSpan));
            self::assertNotEquals($serverTxSpan->traceId, $clientTxSpan->traceId);
        }

        $expectationsForServerTxSpan->assertMatches($serverTxSpan);
        self::assertSame($enableCurlInstrumentationForClient, $serverTxSpan->hasRemoteParent());
    }

    /**
     * @dataProvider dataProviderForTestLocalClientServer
     */
    public function testLocalClientServer(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestLocalClientServer($testArgs);
            }
        );
    }
}
