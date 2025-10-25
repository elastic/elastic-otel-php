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

use CurlHandle;
use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class HttpClientUtilForTests
{
    use StaticClassTrait;

    public const MAX_WAIT_TIME_SECONDS = 10;
    public const CONNECT_TIMEOUT_SECONDS = self::MAX_WAIT_TIME_SECONDS * 2;
    public const TIMEOUT_SECONDS = self::MAX_WAIT_TIME_SECONDS * 2;

    private static ?Logger $logger = null;

    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        }

        return self::$logger;
    }

    public static function sendRequestToAppCode(HttpAppCodeRequestParams $requestParams): ResponseInterface
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $localLogger = self::getLogger()->inherit()->addAllContext(compact('requestParams'));
        $loggerProxyDebug = $localLogger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Sending HTTP request to app code...');

        $response = HttpClientUtilForTests::sendRequest($requestParams->httpRequestMethod, $requestParams->urlParts, $requestParams->dataPerRequest);
        $actualResponseStatusCode = $response->getStatusCode();
        $localLogger->addAllContext(compact('actualResponseStatusCode'));
        $dbgCtx->add(compact('actualResponseStatusCode'));

        if ($requestParams->expectedHttpResponseStatusCode !== null) {
            TestCase::assertSame($requestParams->expectedHttpResponseStatusCode, $actualResponseStatusCode);
        }

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Successfully sent HTTP request to app code');
        return $response;
    }

    /**
     * @param array<string, string> $headers
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function sendRequest(string $httpMethod, UrlParts $urlParts, TestInfraDataPerRequest $dataPerRequest, array $headers = []): ResponseInterface
    {
        $localLogger = self::getLogger()->inherit()->addAllContext(compact('httpMethod', 'urlParts', 'dataPerRequest', 'headers'));
        ($loggerProxyDebug = $localLogger->ifDebugLevelEnabledNoLine(__FUNCTION__));

        $baseUrl = UrlUtil::buildRequestBaseUrl($urlParts);
        $urlRelPart = UrlUtil::buildRequestMethodArg($urlParts);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, "Sending HTTP request...", compact('baseUrl', 'urlRelPart'));

        $client = new Client(['base_uri' => $baseUrl]);
        $response = $client->request(
            $httpMethod,
            $urlRelPart,
            [
                RequestOptions::HEADERS     =>
                    $headers
                    + [RequestHeadersRawSnapshotSource::optionNameToHeaderName(OptionForTestsName::data_per_request->name) => PhpSerializationUtil::serializeToString($dataPerRequest)],
                /*
                 * http://docs.guzzlephp.org/en/stable/request-options.html#http-errors
                 *
                 * http_errors
                 *
                 * Set to false to disable throwing exceptions on HTTP protocol errors (i.e., 4xx and 5xx responses).
                 * Exceptions are thrown by default when HTTP protocol errors are encountered.
                 */
                RequestOptions::HTTP_ERRORS => false,
                /*
                 * https://docs.guzzlephp.org/en/stable/request-options.html#connect-timeout
                 *
                 * connect-timeout
                 *
                 * Float describing the number of seconds to wait while trying to connect to a server.
                 * Use 0 to wait indefinitely (the default behavior).
                 */
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                /*
                 * https://docs.guzzlephp.org/en/stable/request-options.html#timeout
                 *
                 * timeout
                 *
                 * Float describing the total timeout of the request in seconds.
                 * Use 0 to wait indefinitely (the default behavior).
                 */
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
            ]
        );

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Sent HTTP request', ['response status code' => $response->getStatusCode()]);
        return $response;
    }

    /** @noinspection PhpUnused */
    public static function createCurlHandleToSendRequestToAppCode(UrlParts $urlParts, TestInfraDataPerRequest $dataPerRequest, ResourcesClient $resourcesClient): CurlHandleForTests
    {
        $curlInitRetVal = curl_init(UrlUtil::buildFullUrl($urlParts));
        Assert::assertInstanceOf(CurlHandle::class, $curlInitRetVal);
        $curlHandle = new CurlHandleForTests($curlInitRetVal, $resourcesClient);
        $dataPerRequestHeaderName = RequestHeadersRawSnapshotSource::optionNameToHeaderName(OptionForTestsName::data_per_request->name);
        $dataPerRequestHeaderVal = PhpSerializationUtil::serializeToString($dataPerRequest);
        $curlHandle->setOpt(CURLOPT_HTTPHEADER, [$dataPerRequestHeaderName . ': ' . $dataPerRequestHeaderVal]);
        return $curlHandle;
    }

    /**
     * @param array<string, array<string>> $headers
     */
    public static function getSingleHeaderValue(string $headerName, array $headers): string
    {
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists($headerName, $headers, /* out */ $values));
        return ArrayUtilForTests::getSingleValue($values);
    }
}
