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

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\NumericUtil;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtilForTests;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\HttpContentTypes;
use ElasticOTelTests\Util\HttpHeaderNames;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Promise;

final class MockOTelCollectorTestsInfra extends MockOTelCollectorModuleBase
{
    use HttpServerProcessTrait;

    public const MOCK_API_URI_PREFIX = '/mock_OTel_Collector_API/';

    public const GET_AGENT_BACKEND_COMM_EVENTS_URI_SUFFIX = 'get_Agent_Backend_comm_events';
    public const FROM_INDEX_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'FROM_INDEX';
    public const SHOULD_WAIT_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'SHOULD_WAIT';

    public const SET_REMOTE_CONFIG_URI_SUFFIX = 'set_remote_config_file_name_to_content';

    /** @var list<AgentBackendCommEvent> */
    private array $agentBackendCommEvents;

    private int $pendingDataRequestNextId;

    /** @var array<int, MockOTelCollectorPendingDataRequest> */
    private array $pendingDataRequests;

    private readonly Logger $logger;

    public function __construct(MockOTelCollector $parent)
    {
        parent::__construct($parent);

        $this->cleanTestScopedData();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    #[Override]
    public function processRequest(ServerRequestInterface $request): null|ResponseInterface|Promise
    {
        if (TextUtil::isPrefixOf(self::MOCK_API_URI_PREFIX, $request->getUri()->getPath())) {
            return $this->processMockApiRequest($request);
        }

        if ($request->getUri()->getPath() === TestInfraHttpServerProcessBase::CLEAN_TEST_SCOPED_URI_PATH) {
            $this->parent->cleanTestScoped();
            return self::buildOkResponse();
        }

        return null;
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function processMockApiRequest(ServerRequestInterface $request): ResponseInterface|Promise
    {
        return match ($command = substr($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX))) {
            self::GET_AGENT_BACKEND_COMM_EVENTS_URI_SUFFIX => $this->getAgentBackendCommEvents($request),
            self::SET_REMOTE_CONFIG_URI_SUFFIX => $this->parent->opampModule->setAgentRemoteConfig($request),
            default => $this->buildErrorResponse(HttpStatusCodes::NOT_FOUND, 'Unknown Mock API command `' . $command . '\''),
        };
    }

    public function addAgentBackendCommEvent(AgentBackendCommEvent $event): void
    {
        $this->agentBackendCommEvents[] = $event;
        $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__)?->log(__LINE__, 'Added event', ['type' => get_debug_type($event)] + compact('event'));

        $this->onNewAgentBackendCommEvent();
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function getAgentBackendCommEvents(ServerRequestInterface $request): ResponseInterface|Promise
    {
        $fromIndex = intval(self::getRequiredRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        $shouldWait = BoolUtilForTests::fromString(self::getRequiredRequestHeader($request, self::SHOULD_WAIT_HEADER_NAME));
        if (!NumericUtil::isInClosedInterval(0, $fromIndex, count($this->agentBackendCommEvents))) {
            return $this->buildErrorResponse(
                HttpStatusCodes::BAD_REQUEST,
                'Invalid `' . self::FROM_INDEX_HEADER_NAME . '\' HTTP request header value: ' . $fromIndex
                . ' (should be in range[0, ' . count($this->agentBackendCommEvents) . '])'
            );
        }

        if ($this->hasAgentBackendCommEvents($fromIndex) || !$shouldWait) {
            return $this->fulfillGetAgentBackendCommEvents($fromIndex);
        }

        /** @var Promise<ResponseInterface> $promise */
        $promise = new Promise(
        /**
         * @param callable(ResponseInterface): void $callToSendResponse
         */
            function (callable $callToSendResponse) use ($fromIndex): void {
                $pendingDataRequestId = $this->pendingDataRequestNextId++;
                $timer = $this->parent->reactLoop()->addTimer(
                    HttpClientUtilForTests::MAX_WAIT_TIME_SECONDS,
                    function () use ($pendingDataRequestId) {
                        $this->fulfillTimedOutPendingDataRequest($pendingDataRequestId);
                    }
                );
                ArrayUtilForTests::addAssertingKeyNew($pendingDataRequestId, new MockOTelCollectorPendingDataRequest($fromIndex, $callToSendResponse, $timer), /* n,out */ $this->pendingDataRequests);
            }
        );
        return $promise;
    }

    private function hasAgentBackendCommEvents(int $fromIndex): bool
    {
        return count($this->agentBackendCommEvents) > $fromIndex;
    }

    private function onNewAgentBackendCommEvent(): void
    {
        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->parent->reactLoop()->cancelTimer($pendingDataRequest->timer);
            ($pendingDataRequest->callToSendResponse)($this->fulfillGetAgentBackendCommEvents($pendingDataRequest->fromIndex));
        }
        $this->pendingDataRequests = [];
    }

    private function fulfillGetAgentBackendCommEvents(int $fromIndex): ResponseInterface
    {
        $newEvents = $this->hasAgentBackendCommEvents($fromIndex) ? array_slice($this->agentBackendCommEvents, $fromIndex) : [];

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Sending response ...', ['fromIndex' => $fromIndex, 'newEvents count' => count($newEvents)]);

        return self::encodeGetAgentBackendCommEvents($newEvents);
    }

    /**
     * @param list<AgentBackendCommEvent> $events
     *
     * @see self::decodeGetEventsResponse
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function encodeGetAgentBackendCommEvents(array $events): ResponseInterface
    {
        return new Response(
            status: HttpStatusCodes::OK,
            headers: [HttpHeaderNames::CONTENT_TYPE => HttpContentTypes::JSON],
            body: JsonUtil::encode([AgentBackendCommEventsBlock::class => PhpSerializationUtil::serializeToString(new AgentBackendCommEventsBlock($events))]),
        );
    }

    /**
     * @return list<AgentBackendCommEvent>
     *
     * @see self::encodeGetEventsResponse
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function decodeGetAgentBackendCommEvents(ResponseInterface $response): array
    {
        $responseBody = $response->getBody()->getContents();
        $contentType = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_TYPE, $response->getHeaders()); // @phpstan-ignore argument.type
        $dbgCtx = ['expected status code' => HttpStatusCodes::OK, 'actual status code' => $response->getStatusCode()];
        ArrayUtilForTests::append(['expected content type' => HttpContentTypes::JSON], to: $dbgCtx);
        ArrayUtilForTests::append(compact('contentType', 'responseBody'), to: $dbgCtx);
        if ($response->getStatusCode() !== HttpStatusCodes::OK) {
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Unexpected status code', $dbgCtx));
        }
        if ($contentType !== HttpContentTypes::JSON) {
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Unexpected content type', $dbgCtx));
        }

        $responseBodyDecodedJson = JsonUtil::decode($responseBody);
        Assert::assertIsArray($responseBodyDecodedJson);
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists(AgentBackendCommEventsBlock::class, $responseBodyDecodedJson, /* out */ $newEventsWrappedSerialized));
        Assert::assertIsString($newEventsWrappedSerialized);
        return PhpSerializationUtil::unserializeFromStringAssertType($newEventsWrappedSerialized, AgentBackendCommEventsBlock::class)->events;
    }

    private function fulfillTimedOutPendingDataRequest(int $pendingDataRequestId): void
    {
        if (!ArrayUtil::removeValue($pendingDataRequestId, $this->pendingDataRequests, /* out */ $pendingDataRequest)) {
            // If request is already fulfilled then just return
            return;
        }

        ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Timed out while waiting for ' . self::GET_AGENT_BACKEND_COMM_EVENTS_URI_SUFFIX . ' to be fulfilled - returning empty data set...', compact('pendingDataRequestId'));

        ($pendingDataRequest->callToSendResponse)($this->fulfillGetAgentBackendCommEvents($pendingDataRequest->fromIndex));
    }

    /**
     * Extracted to a separate method because it is called from __construct() before $logger is initialized
     */
    private function cleanTestScopedData(): void
    {
        $this->agentBackendCommEvents = [];

        $this->pendingDataRequestNextId = 1;
        $this->pendingDataRequests = [];
    }

    public function cleanTestScoped(): void
    {
        $this->cleanTestScopedData();
    }

    public function exit(): void
    {
        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->parent->reactLoop()->cancelTimer($pendingDataRequest->timer);
        }
    }
}
