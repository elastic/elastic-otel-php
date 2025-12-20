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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

final class MockOTelCollectorSignalIntake extends MockOTelCollectorModuleBase
{
    use HttpServerProcessTrait;

    private const INTAKE_LOGS_DATA_URI_PATH = '/v1/logs';
    private const INTAKE_METRICS_DATA_URI_PATH = '/v1/metrics';
    private const INTAKE_TRACE_DATA_URI_PATH = '/v1/traces';

    private readonly Logger $logger;

    public function __construct(MockOTelCollector $parent)
    {
        parent::__construct($parent);

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
    }

    #[Override]
    public function processRequest(ServerRequestInterface $request): null|ResponseInterface
    {
        $signalType = match ($request->getUri()->getPath()) {
            self::INTAKE_LOGS_DATA_URI_PATH => OTelSignalType::log,
            self::INTAKE_METRICS_DATA_URI_PATH => OTelSignalType::metric,
            self::INTAKE_TRACE_DATA_URI_PATH => OTelSignalType::trace,
            default => null,
        };

        return $signalType === null ? null : $this->processIntakeDataRequest($request, $signalType);
    }

    private function processIntakeDataRequest(ServerRequestInterface $request, OTelSignalType $signalType): ResponseInterface
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

        $intakeDataRequestRaw = new IntakeDataRequestRaw(
            MockOTelCollector::getPortByIndex(MockOTelCollector::SIGNAL_INTAKE_PORT_INDEX),
            AmbientContextForTests::clock()->getMonotonicClockCurrentTime(),
            AmbientContextForTests::clock()->getSystemClockCurrentTime(),
            $signalType,
            $request->getHeaders(), // @phpstan-ignore argument.type
            $body,
        );

        $deserializedRequest = AgentBackendCommsAccumulator::deserializeIntakeDataRequestBody($intakeDataRequestRaw);
        $logDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logDebug?->log(__LINE__, 'Deserialized intake data request', compact('deserializedRequest'));

        if ($deserializedRequest->isEmptyAfterDeserialization()) {
            $logDebug && $logDebug->log(__LINE__, 'All data has been discarded by deserialization');
        }

        $this->parent->testsInfraModule->addAgentBackendCommEvent($intakeDataRequestRaw);

        return new Response(HttpStatusCodes::ACCEPTED);
    }
}
