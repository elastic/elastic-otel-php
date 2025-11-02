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
use ElasticOTelTests\Util\AssertEx;
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

/**
 * @phpstan-import-type HttpHeaders from IntakeDataRequestRaw
 */
final class MockOTelCollector extends TestInfraHttpServerProcessBase
{
    public const MOCK_API_URI_PREFIX = '/mock_OTel_Collector_API/';
    private const INTAKE_TRACE_DATA_URI_PATH = '/v1/traces';
    public const GET_AGENT_BACKEND_COMM_EVENTS_URI_SUBPATH = 'get_Agent_Backend_comm_events';
    public const FROM_INDEX_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'FROM_INDEX';
    public const SHOULD_WAIT_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'SHOULD_WAIT';

    /** @var list<AgentBackendCommEvent> */
    private array $agentBackendCommEvents = [];
    public int $pendingDataRequestNextId;
    /** @var array<int, MockOTelCollectorPendingDataRequest> */
    private array $pendingDataRequests = [];
    private readonly Logger $logger;
    private Clock $clock;

    public function __construct()
    {
        $this->clock = new Clock(AmbientContextForTests::loggerFactory());
        $this->cleanTestScopedData();

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
            $newEvent = new AgentBackendConnectionStarted(
                $this->clock->getMonotonicClockCurrentTime(),
                $this->clock->getSystemClockCurrentTime(),
            );
            $this->addAgentBackendCommEvent($newEvent);
        }
    }

    private function addAgentBackendCommEvent(AgentBackendCommEvent $event): void
    {
        Assert::assertNotNull($this->reactLoop);
        $this->agentBackendCommEvents[] = $event;

        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->reactLoop->cancelTimer($pendingDataRequest->timer);
            ($pendingDataRequest->callToSendResponse)($this->fulfillGetAgentBackendCommEvents($pendingDataRequest->fromIndex));
        }
        $this->pendingDataRequests = [];
    }

    /** @inheritDoc */
    #[Override]
    protected function processRequest(ServerRequestInterface $request): null|ResponseInterface|Promise
    {
        if ($request->getUri()->getPath() === self::INTAKE_TRACE_DATA_URI_PATH) {
            return $this->processIntakeDataRequest($request, OTelSignalType::trace);
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
        return $request->getUri()->getPath() !== self::INTAKE_TRACE_DATA_URI_PATH;
    }

    /**
     * @param HttpHeaders $httpHeaders
     */
    private static function verifyIntakeDataRequest(array $httpHeaders, int $bodySize): void
    {
        $contentLength = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_LENGTH, $httpHeaders);
        AssertEx::stringSameAsInt($bodySize, $contentLength);

        $contentType = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_TYPE, $httpHeaders);
        Assert::assertSame(HttpContentTypes::PROTOBUF, $contentType);
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function processIntakeDataRequest(ServerRequestInterface $request, OTelSignalType $signalType): ResponseInterface
    {
        Assert::assertNotNull($this->reactLoop);

        $body = $request->getBody()->getContents();
        $bodySize = strlen($body);
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('bodySize'));
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Deserializing intake trace data request');
        Assert::assertSame($bodySize, $request->getBody()->getSize());

        if ($bodySize === 0) {
            return $this->buildIntakeDataErrorResponse(/* status */ HttpStatusCodes::BAD_REQUEST, 'Intake API request should not have empty body');
        }

        self::verifyIntakeDataRequest($request->getHeaders(), $bodySize); // @phpstan-ignore argument.type

        $intakeDataRequestRaw = new IntakeDataRequestRaw(
            AmbientContextForTests::clock()->getMonotonicClockCurrentTime(),
            AmbientContextForTests::clock()->getSystemClockCurrentTime(),
            $signalType,
            $request->getHeaders(), // @phpstan-ignore argument.type
            $body
        );

        $deserializedRequest = AgentBackendCommsAccumulator::deserializeIntakeDataRequestBody($intakeDataRequestRaw);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Deserialized intake data request', compact('deserializedRequest'));

        if ($deserializedRequest->isEmptyAfterDeserialization()) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'All data has been discarded by deserialization');
        }

        $this->addAgentBackendCommEvent($intakeDataRequestRaw);

        return new Response(/* status: */ 202);
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function processMockApiRequest(ServerRequestInterface $request): Promise|ResponseInterface
    {
        return match ($command = substr($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX))) {
            self::GET_AGENT_BACKEND_COMM_EVENTS_URI_SUBPATH => $this->getAgentBackendCommEvents($request),
            default => $this->buildErrorResponse(HttpStatusCodes::BAD_REQUEST, 'Unknown Mock API command `' . $command . '\''),
        };
    }

    /**
     * @return ResponseInterface|Promise<ResponseInterface>
     */
    private function getAgentBackendCommEvents(ServerRequestInterface $request): Promise|ResponseInterface
    {
        $fromIndex = intval(self::getRequiredRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        $shouldWait = BoolUtil::fromString(self::getRequiredRequestHeader($request, self::SHOULD_WAIT_HEADER_NAME));
        if (!NumericUtil::isInClosedInterval(0, $fromIndex, count($this->agentBackendCommEvents))) {
            return $this->buildErrorResponse(
                HttpStatusCodes::BAD_REQUEST /* status */,
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

    private function hasAgentBackendCommEvents(int $fromIndex): bool
    {
        return count($this->agentBackendCommEvents) > $fromIndex;
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

        $responseBodyDecodedJson = JsonUtil::decode($responseBody, asAssocArray: true);
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
        && $loggerProxy->log('Timed out while waiting for ' . self::GET_AGENT_BACKEND_COMM_EVENTS_URI_SUBPATH . ' to be fulfilled - returning empty data set...', compact('pendingDataRequestId'));

        ($pendingDataRequest->callToSendResponse)($this->fulfillGetAgentBackendCommEvents($pendingDataRequest->fromIndex));
    }

    protected function buildIntakeDataErrorResponse(int $status, string $message): ResponseInterface
    {
        return new Response(status: $status, headers: [HttpHeaderNames::CONTENT_TYPE => HttpContentTypes::TEXT], body: $message);
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

    private function cleanTestScoped(): void
    {
        $beforeClean = MemoryUtil::logMemoryUsage('Before cleaning test scoped');

        $this->cleanTestScopedData();
        MemoryUtil::logMemoryUsage('After cleaning test scoped', $beforeClean);

        $collectedCyclesCount = gc_collect_cycles();
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log("gc_collect_cycles() returned $collectedCyclesCount");
        MemoryUtil::logMemoryUsage('After calling gc_collect_cycles()', $beforeClean);
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
