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

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\NumericUtil;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Clock;
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
use React\Socket\ConnectionInterface;

final class MockOTelCollector extends TestInfraHttpServerProcessBase
{
    public const MOCK_API_URI_PREFIX = '/mock_OTel_Collector_API/';
    private const INTAKE_API_URI = '/v1/traces';
    public const GET_AGENT_TO_OTEL_COLLECTOR_EVENTS_URI_SUBPATH = 'get_Agent_to_OTel_Collector_events';
    public const FROM_INDEX_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'FROM_INDEX';
    public const SHOULD_WAIT_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'SHOULD_WAIT';

    /** @var AgentToOTeCollectorEvent[] */
    private array $agentToOTeCollectorEvents = [];
    public int $pendingDataRequestNextId;
    /** @var array<int, MockOTelCollectorPendingDataRequest> */
    private array $pendingDataRequests = [];
    private readonly Logger $logger;
    private Clock $clock;

    public function __construct()
    {
        $this->clock = new Clock(AmbientContextForTests::loggerFactory());
        $this->cleanTestScoped();

        /** @noinspection PhpUnhandledExceptionInspection */
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    protected function expectedPortsCount(): int
    {
        return 2;
    }

    #[Override]
    protected function onNewConnection(int $socketIndex, ConnectionInterface $connection): void
    {
        parent::onNewConnection($socketIndex, $connection);
        Assert::assertCount(2, $this->serverSockets);
        Assert::assertLessThan(count($this->serverSockets), $socketIndex);

        // $socketIndex 0 is used for test infrastructure communication
        // $socketIndex 1 is used for APM Agent <-> Server communication
        if ($socketIndex == 1) {
            $newEvent = new AgentToOTelCollectorConnectionStarted(
                $this->clock->getMonotonicClockCurrentTime(),
                $this->clock->getSystemClockCurrentTime(),
            );
            $this->addAgentToOTeCollectorEvent($newEvent);
        }
    }

    private function addAgentToOTeCollectorEvent(AgentToOTeCollectorEvent $event): void
    {
        Assert::assertNotNull($this->reactLoop);
        $this->agentToOTeCollectorEvents[] = $event;

        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->reactLoop->cancelTimer($pendingDataRequest->timer);
            ($pendingDataRequest->callToSendResponse)($this->fulfillDataRequest($pendingDataRequest->fromIndex));
        }
        $this->pendingDataRequests = [];
    }

    /** @inheritDoc */
    #[Override]
    protected function processRequest(ServerRequestInterface $request): null|ResponseInterface|Promise
    {
        if ($request->getUri()->getPath() === self::INTAKE_API_URI) {
            return $this->processIntakeApiRequest($request);
        }

        if (TextUtil::isPrefixOf(self::MOCK_API_URI_PREFIX, $request->getUri()->getPath())) {
            return $this->processMockApiRequest($request);
        }

        if ($request->getUri()->getPath() === TestInfraHttpServerProcessBase::CLEAN_TEST_SCOPED_URI_PATH) {
            $this->cleanTestScoped();
            return new Response(/* status: */ 200);
        }

        return null;
    }

    #[Override]
    protected function shouldRequestHaveSpawnedProcessInternalId(ServerRequestInterface $request): bool
    {
        return $request->getUri()->getPath() !== self::INTAKE_API_URI;
    }

    private function processIntakeApiRequest(ServerRequestInterface $request): ResponseInterface
    {
        Assert::assertNotNull($this->reactLoop);

        if ($request->getBody()->getSize() === 0) {
            return $this->buildIntakeApiErrorResponse(/* status */ HttpStatusCodes::BAD_REQUEST, 'Intake API request should not have empty body');
        }

        $newRequest = new IntakeApiRequest(
            AmbientContextForTests::clock()->getMonotonicClockCurrentTime(),
            AmbientContextForTests::clock()->getSystemClockCurrentTime(),
            $request->getHeaders(), // @phpstan-ignore argument.type
            base64_encode($request->getBody()->getContents()),
        );

        ($loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__))
        && $loggerProxyDebug->log(__LINE__, 'Received request for Intake API', ['newRequest' => $newRequest]);

        if ($loggerProxyDebug !== null) {
            $exportedData = IntakeApiRequestDeserializer::deserialize($newRequest);
            if ($exportedData->isEmpty()) {
                $loggerProxyDebug->log(__LINE__, 'All of the contents has been discarded');
            } else {
                $loggerProxyDebug->log(__LINE__, 'Contents', compact('exportedData'));
            }
        }

        $this->addAgentToOTeCollectorEvent($newRequest);

        return new Response(/* status: */ 202);
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function processMockApiRequest(ServerRequestInterface $request): Promise|ResponseInterface
    {
        return match ($command = substr($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX))) {
            self::GET_AGENT_TO_OTEL_COLLECTOR_EVENTS_URI_SUBPATH => $this->getIntakeApiRequests($request),
            default => $this->buildErrorResponse(HttpStatusCodes::BAD_REQUEST, 'Unknown Mock API command `' . $command . '\''),
        };
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function getIntakeApiRequests(ServerRequestInterface $request): Promise|ResponseInterface
    {
        $fromIndex = intval(self::getRequiredRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        $shouldWait = BoolUtil::fromString(self::getRequiredRequestHeader($request, self::SHOULD_WAIT_HEADER_NAME));
        if (!NumericUtil::isInClosedInterval(0, $fromIndex, count($this->agentToOTeCollectorEvents))) {
            return $this->buildErrorResponse(
                HttpStatusCodes::BAD_REQUEST /* status */,
                'Invalid `' . self::FROM_INDEX_HEADER_NAME . '\' HTTP request header value: ' . $fromIndex
                . ' (should be in range[0, ' . count($this->agentToOTeCollectorEvents) . '])'
            );
        }

        if ($this->hasNewDataFromAgentRequest($fromIndex) || !$shouldWait) {
            return $this->fulfillDataRequest($fromIndex);
        }

        /** @var Promise<ResponseInterface> $promise */
        $promise = new Promise(
            /**
             * @param callable(ResponseInterface): void $callToSendResponse
             */
            function (callable $callToSendResponse) use ($fromIndex): void {
                $pendingDataRequestId = $this->pendingDataRequestNextId++;
                Assert::assertNotNull($this->reactLoop);
                $timer = $this->reactLoop->addTimer(
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

    private function hasNewDataFromAgentRequest(int $fromIndex): bool
    {
        return count($this->agentToOTeCollectorEvents) > $fromIndex;
    }

    private function fulfillDataRequest(int $fromIndex): ResponseInterface
    {
        $newEvents = $this->hasNewDataFromAgentRequest($fromIndex) ? array_slice($this->agentToOTeCollectorEvents, $fromIndex) : [];

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Sending response ...', ['fromIndex' => $fromIndex, 'newEvents count' => count($newEvents)]);

        return self::encodeResponse($newEvents);
    }

    /**
     * @param AgentToOTeCollectorEvent[] $events
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private static function encodeResponse(array $events): ResponseInterface
    {
        $eventsWrapped = new AgentToOTeCollectorEvents($events);
        return new Response(
            status:  HttpStatusCodes::OK,
            headers: [HttpHeaderNames::CONTENT_TYPE => HttpContentTypes::JSON],
            body:    JsonUtil::encode([AgentToOTeCollectorEvents::class => PhpSerializationUtil::serializeToString($eventsWrapped)])
        );
    }

    /**
     * @return AgentToOTeCollectorEvent[]
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function decodeResponse(ResponseInterface $response): array
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

        $responseBodyDecodedJson = JsonUtil::decode($responseBody, asAssocArray: true);
        Assert::assertIsArray($responseBodyDecodedJson);
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists(AgentToOTeCollectorEvents::class, $responseBodyDecodedJson, /* out */ $newEventsWrappedSerialized));
        Assert::assertIsString($newEventsWrappedSerialized);
        return PhpSerializationUtil::unserializeFromStringAssertType($newEventsWrappedSerialized, AgentToOTeCollectorEvents::class)->events;
    }

    private function fulfillTimedOutPendingDataRequest(int $pendingDataRequestId): void
    {

        if (!ArrayUtil::removeValue($pendingDataRequestId, $this->pendingDataRequests, /* out */ $pendingDataRequest)) {
            // If request is already fulfilled then just return
            return;
        }

        ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Timed out while waiting for ' . self::GET_AGENT_TO_OTEL_COLLECTOR_EVENTS_URI_SUBPATH . ' to be fulfilled - returning empty data set...', compact('pendingDataRequestId'));

        ($pendingDataRequest->callToSendResponse)($this->fulfillDataRequest($pendingDataRequest->fromIndex));
    }

    protected function buildIntakeApiErrorResponse(int $status, string $message): ResponseInterface
    {
        return new Response(status: $status, headers: [HttpHeaderNames::CONTENT_TYPE => HttpContentTypes::TEXT], body: $message);
    }

    private function cleanTestScoped(): void
    {
        $this->agentToOTeCollectorEvents = [];
        $this->pendingDataRequestNextId = 1;
        $this->pendingDataRequests = [];
    }

    #[Override]
    protected function exit(): void
    {
        Assert::assertNotNull($this->reactLoop);

        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->reactLoop->cancelTimer($pendingDataRequest->timer);
        }

        parent::exit();
    }
}
