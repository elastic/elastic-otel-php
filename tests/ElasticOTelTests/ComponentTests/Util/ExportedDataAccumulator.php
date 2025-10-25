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
use ElasticOTelTests\Util\Log\LoggableTrait;
use PHPUnit\Framework\Assert;

final class ExportedDataAccumulator implements LoggableInterface
{
    use LoggableTrait;

    /** @var IntakeDataConnection[] */
    private array $closedIntakeDataConnections = [];

    private ?AgentToOTelCollectorConnectionStarted $openIntakeDataConnection = null;

    /** @var IntakeTraceDataRequest[] */
    private array $openIntakeDataConnectionRequests = [];

    /**
     * @param AgentToOTeCollectorEvent[] $events
     */
    public function addAgentToOTeCollectorEvents(array $events): void
    {
        foreach ($events as $event) {
            if ($event instanceof IntakeTraceDataRequest) {
                $this->addIntakeDataRequest($event);
            } elseif ($event instanceof AgentToOTelCollectorConnectionStarted) {
                $this->addNewConnection($event);
            }
        }
    }

    private function addNewConnection(AgentToOTelCollectorConnectionStarted $event): void
    {
        if ($this->openIntakeDataConnection === null) {
            Assert::assertCount(0, $this->openIntakeDataConnectionRequests);
        } else {
            $this->closedIntakeDataConnections[] = new IntakeDataConnection($this->openIntakeDataConnection, $this->openIntakeDataConnectionRequests);
            $this->openIntakeDataConnectionRequests = [];
        }

        $this->openIntakeDataConnection = $event;
    }

    private function addIntakeDataRequest(IntakeTraceDataRequest $intakeDataRequest): void
    {
        $this->openIntakeDataConnectionRequests[] = $intakeDataRequest;
    }

    public function isEnough(IsEnoughExportedDataInterface $isEnoughExportedData): bool
    {
        return $isEnoughExportedData->isEnough($this->getAccumulatedData()->spans());
    }

    public function getAccumulatedData(): ExportedData
    {
        $intakeDataConnections = $this->closedIntakeDataConnections;
        if ($this->openIntakeDataConnection !== null) {
            $intakeDataConnections[] = new IntakeDataConnection($this->openIntakeDataConnection, $this->openIntakeDataConnectionRequests);
        }
        return new ExportedData($intakeDataConnections);
    }
}
