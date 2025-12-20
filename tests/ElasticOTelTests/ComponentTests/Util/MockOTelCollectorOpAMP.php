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

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\ComponentTests\Util\OpampData\AgentRemoteConfig;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentToServer;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\HttpContentTypes;
use ElasticOTelTests\Util\HttpHeaderNames;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use GeneratedForElasticOTelTests\OpampProto\ServerCapabilities as ProtoServerCapabilities;
use GeneratedForElasticOTelTests\OpampProto\ServerToAgent as ProtoServerToAgent;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MockOTelCollectorOpAMP extends MockOTelCollectorModuleBase
{
    public const URI_PATH = '/v1/opamp';

    private ?AgentRemoteConfig $agentRemoteConfig;

    private readonly Logger $logger;

    public function __construct(MockOTelCollector $parent)
    {
        parent::__construct($parent);

        $this->cleanTestScoped();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    #[Override]
    public function processRequest(ServerRequestInterface $request): null|ResponseInterface
    {
        if ($request->getUri()->getPath() === self::URI_PATH) {
            return $this->processOpampRequest($request);
        }

        return null;
    }


    /**
     * @see MockOTelCollectorHandle::setAgentRemoteConfig
     */
    public function setAgentRemoteConfig(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getBody()->getContents();
        $contentType = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_TYPE, $request->getHeaders()); // @phpstan-ignore argument.type
        $dbgCtx = ['expected content type' => HttpContentTypes::JSON];
        ArrayUtilForTests::append(compact('contentType', 'requestBody'), to: $dbgCtx);
        Assert::assertSame(HttpContentTypes::PHP_SERIALIZED, $contentType);
        $this->agentRemoteConfig = PhpSerializationUtil::unserializeFromStringAssertType($requestBody, AgentRemoteConfig::class);
        return self::buildOkResponse();
    }

    private function processOpampRequest(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getBody()->getContents();
        $bodySize = strlen($body);
        Assert::assertSame($bodySize, $request->getBody()->getSize());

        if ($bodySize === 0) {
            return $this->buildErrorResponse(HttpStatusCodes::BAD_REQUEST, 'Intake API request should not have empty body');
        }

        if (($response = self::verifyPostProtoBufRequest($request, $bodySize)) !== null) {
            return $response;
        }

        $agentToServer = AgentToServer::deserialize($body);
        $logDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logDebug?->log(__LINE__, 'Deserialized OpAPM AgentToServer', compact('agentToServer'));

        $this->parent->testsInfraModule->addAgentBackendCommEvent(
            new OpampAgentToServerRequest(
                MockOTelCollector::getPortByIndex(MockOTelCollector::OPAMP_PORT_INDEX),
                AmbientContextForTests::clock()->getMonotonicClockCurrentTime(),
                AmbientContextForTests::clock()->getSystemClockCurrentTime(),
                $agentToServer,
            ),
        );

        // AgentCapabilities are meaningful only when agentDescription !== null which means the full state is reported
        if ($agentToServer->agentDescription !== null && !$agentToServer->agentCapabilities->acceptsRemoteConfig()) {
            return $this->buildErrorResponse(HttpStatusCodes::EXPECTATION_FAILED, 'Agent capabilities does not include AcceptsRemoteConfig ; ' . JsonUtil::encode(compact('agentToServer')));
        }

        $protoServerToAgent = new ProtoServerToAgent();
        $protoServerToAgent->setInstanceUid($agentToServer->instanceUid);
        $protoServerToAgent->setFlags(0);
        $protoServerToAgent->setCapabilities(ProtoServerCapabilities::ServerCapabilities_AcceptsStatus | ProtoServerCapabilities::ServerCapabilities_OffersRemoteConfig);

        if ($this->agentRemoteConfig !== null && !$this->agentRemoteConfig->wasAlreadySentToAgent($agentToServer)) {
            $protoServerToAgent->setRemoteConfig($this->agentRemoteConfig->toProto());
            $logDebug?->log(__LINE__, 'Added remote config to response');
        }

        return self::buildProtobufResponse(HttpStatusCodes::OK, $protoServerToAgent->serializeToString());
    }

    public function cleanTestScoped(): void
    {
        $this->agentRemoteConfig = null;
    }
}
