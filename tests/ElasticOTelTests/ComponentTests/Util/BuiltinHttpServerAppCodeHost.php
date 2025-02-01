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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\GlobalUnderscoreServer;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

final class BuiltinHttpServerAppCodeHost extends AppCodeHostBase
{
    use HttpServerProcessTrait;

    private readonly Logger $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Received request', ['URI' => GlobalUnderscoreServer::requestUri(), 'method' => GlobalUnderscoreServer::requestMethod()]);
    }

    protected static function isStatusCheck(): bool
    {
        return GlobalUnderscoreServer::requestUri() === HttpServerHandle::STATUS_CHECK_URI_PATH;
    }

    #[Override]
    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        // We should register with ResourcesCleaner only on the status-check request
        return self::isStatusCheck();
    }

    #[Override]
    protected function processConfig(): void
    {
        Assert::assertCount(1, AmbientContextForTests::testConfig()->dataPerProcess()->thisServerPorts);

        parent::processConfig();

        AmbientContextForTests::reconfigure(new RequestHeadersRawSnapshotSource(fn(string $headerName) => GlobalUnderscoreServer::getRequestHeaderValue($headerName)));
    }

    #[Override]
    protected function runImpl(): void
    {
        $dataPerRequest = AmbientContextForTests::testConfig()->dataPerRequest();
        if (($response = self::verifySpawnedProcessInternalId($dataPerRequest->spawnedProcessInternalId)) !== null) {
            self::sendResponse($response);
            return;
        }
        if (self::isStatusCheck()) {
            self::sendResponse(self::buildResponseWithPid());
            return;
        }

        $this->callAppCode();
    }

    private static function sendResponse(ResponseInterface $response): void
    {
        $localLogger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyDebug = $localLogger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $httpResponseStatusCode = $response->getStatusCode();
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Sending response ...', compact('httpResponseStatusCode', 'response'));

        http_response_code($httpResponseStatusCode);
        echo $response->getBody();
    }
}
