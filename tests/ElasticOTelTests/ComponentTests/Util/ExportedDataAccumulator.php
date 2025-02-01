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

use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use PHPUnit\Framework\Assert;

final class ExportedDataAccumulator implements LoggableInterface
{
    use LoggableTrait;

    /** @var IntakeApiConnection[] */
    private array $closedIntakeApiConnections = [];

    private ?AgentToOTelCollectorConnectionStarted $openIntakeApiConnection = null;

    /** @var IntakeApiRequest[] */
    private array $openIntakeApiConnectionRequests = [];

    /** @var Span[] */
    private array $spans = [];

    /**
     * @param AgentToOTeCollectorEvent[] $events
     */
    public function addAgentToOTeCollectorEvents(array $events): void
    {
        foreach ($events as $event) {
            if ($event instanceof IntakeApiRequest) {
                $this->addIntakeApiRequest($event);
            } elseif ($event instanceof AgentToOTelCollectorConnectionStarted) {
                $this->addNewConnection($event);
            }
        }
    }

    private function addNewConnection(AgentToOTelCollectorConnectionStarted $event): void
    {
        if ($this->openIntakeApiConnection === null) {
            Assert::assertCount(0, $this->openIntakeApiConnectionRequests);
        } else {
            $this->closedIntakeApiConnections[] = new IntakeApiConnection($this->openIntakeApiConnection, $this->openIntakeApiConnectionRequests);
            $this->openIntakeApiConnectionRequests = [];
        }

        $this->openIntakeApiConnection = $event;
    }

    private function addIntakeApiRequest(IntakeApiRequest $intakeApiRequest): void
    {
        $this->openIntakeApiConnectionRequests[] = $intakeApiRequest;

        $newDataParsed = IntakeApiRequestDeserializer::deserialize($intakeApiRequest);
        ArrayUtilForTests::append(from: $newDataParsed->spans, to: $this->spans);
    }

    public function isEnough(IsEnoughExportedDataInterface $isEnoughExportedData): bool
    {
        return $isEnoughExportedData->isEnough($this->spans);
    }

    public function getAccumulatedData(): ExportedData
    {
        $intakeApiConnections = $this->closedIntakeApiConnections;
        if ($this->openIntakeApiConnection !== null) {
            $intakeApiConnections[] = new IntakeApiConnection($this->openIntakeApiConnection, $this->openIntakeApiConnectionRequests);
        }
        return new ExportedData(new RawExportedData($intakeApiConnections), $this->spans);
    }
}
