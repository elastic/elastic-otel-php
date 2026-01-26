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
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use PHPUnit\Framework\Assert;

final class AgentBackendCommsAccumulator implements LoggableInterface
{
    use LoggableTrait;

    /** @var list<AgentBackendConnection> */
    private array $closedConnections = [];

    /** @var array<int, AgentBackendConnectionBuilder> */
    private array $openConnections = [];

    private ?AgentBackendComms $cachedResult = null;

    private string $dbgName; // @phpstan-ignore property.onlyWritten
    private readonly Logger $logger;

    public function __construct()
    {
        $this->dbgName = DbgProcessNameGenerator::generate(ClassNameUtil::fqToShort(__CLASS__));
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /**
     * @param iterable<AgentBackendCommEvent> $events
     */
    public function addEvents(iterable $events): void
    {
        $this->cachedResult = null;

        foreach ($events as $event) {
            $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)?->log('Adding ...', compact('event'));
            match (true) {
                $event instanceof AgentBackendConnectionStarted => $this->onConnectionStarted($event),
                $event instanceof IntakeDataRequestRaw => $this->addRequest($event->port, self::deserializeIntakeDataRequestBody($event)),
                $event instanceof OpampAgentToServerRequest => $this->addRequest($event->port, $event),
                default => throw new ComponentTestsInfraException('Unexpected event type: ' . get_debug_type($events) . '; ' . LoggableToString::convert(compact('event'))),
            };
        }
    }

    private function onConnectionStarted(AgentBackendConnectionStarted $event): void
    {
        if (ArrayUtil::getValueIfKeyExists($event->port, $this->openConnections, /* out */ $openConnectionBuilder)) {
            $this->closedConnections[] = $openConnectionBuilder->build();
            $openConnectionBuilder->reset($event);
        } else {
            $this->openConnections[$event->port] = new AgentBackendConnectionBuilder($event);
        }
    }

    private function addRequest(int $port, AgentBackendCommRequestInterface $request): void
    {
        Assert::assertTrue(ArrayUtil::getValueIfKeyExists($port, $this->openConnections, /* out */ $openConnectionBuilder));
        /** @var AgentBackendConnectionBuilder $openConnectionBuilder */
        $openConnectionBuilder->addRequest($request);
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public static function deserializeIntakeDataRequestBodyToProto(IntakeDataRequestRaw $requestRaw): mixed
    {
        return match ($requestRaw->signalType) {
            OTelSignalType::trace => IntakeTraceDataRequest::deserializeFromRawToProto($requestRaw),
            default => throw new ComponentTestsInfraException('Unexpected OTel signal type: ' . $requestRaw->signalType->name),
        };
    }

    public static function deserializeIntakeDataRequestBody(IntakeDataRequestRaw $requestRaw): IntakeDataRequestDeserialized
    {
        return match ($requestRaw->signalType) {
            OTelSignalType::trace => IntakeTraceDataRequest::deserializeFromRaw($requestRaw),
            default => throw new ComponentTestsInfraException('Unexpected OTel signal type: ' . $requestRaw->signalType->name),
        };
    }

    public function isEnough(IsEnoughAgentBackendCommsInterface $isEnoughAgentBackendComms): bool
    {
        return $isEnoughAgentBackendComms->isEnough($this->getResult());
    }

    public function getResult(): AgentBackendComms
    {
        if ($this->cachedResult === null) {
            $connections = $this->closedConnections;
            foreach ($this->openConnections as $openConnectionBuilder) {
                $connections[] = $openConnectionBuilder->build();
            }
            $this->cachedResult = new AgentBackendComms($connections);
        }

        return $this->cachedResult;
    }
}
