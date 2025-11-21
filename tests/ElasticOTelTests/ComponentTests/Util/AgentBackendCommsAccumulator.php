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

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use PHPUnit\Framework\Assert;

final class AgentBackendCommsAccumulator implements LoggableInterface
{
    use LoggableTrait;

    /** @var list<AgentBackendCommEvent> */
    private array $events = [];

    /** @var list<AgentBackendConnection> */
    private array $closedConnections = [];

    private ?AgentBackendConnectionStarted $openConnectionStart = null;

    /** @var list<IntakeDataRequestDeserialized> */
    private array $openConnectionRequests = [];

    private ?AgentBackendComms $cachedResult = null;

    /**
     * @param iterable<AgentBackendCommEvent> $events
     */
    public function addEvents(iterable $events): void
    {
        $this->cachedResult = null;

        foreach ($events as $event) {
            match (true) {
                $event instanceof AgentBackendConnectionStarted => $this->onConnectionStarted($event),
                $event instanceof IntakeDataRequestRaw => $this->onIntakeDataRequest($event),
                default => throw new ComponentTestsInfraException('Unexpected event type: ' . get_debug_type($events) . '; ' . LoggableToString::convert(compact('event'))),
            };
        }
    }

    private function onConnectionStarted(AgentBackendConnectionStarted $event): void
    {
        if ($this->openConnectionStart === null) {
            Assert::assertCount(0, $this->openConnectionRequests);
        } else {
            $this->closedConnections[] = new AgentBackendConnection($this->openConnectionStart, $this->openConnectionRequests);
            $this->openConnectionRequests = [];
        }

        $this->openConnectionStart = $event;
    }

    private function onIntakeDataRequest(IntakeDataRequestRaw $requestRaw): void
    {
        $this->openConnectionRequests[] = self::deserializeIntakeDataRequestBody($requestRaw);
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
            if ($this->openConnectionStart !== null) {
                $connections[] = new AgentBackendConnection($this->openConnectionStart, $this->openConnectionRequests);
            }
            $this->cachedResult = new AgentBackendComms($this->events, $connections);
        }

        return $this->cachedResult;
    }
}
