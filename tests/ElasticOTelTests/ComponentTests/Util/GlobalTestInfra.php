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

use ElasticOTelTests\Util\ClassNameUtil;
use PHPUnit\Framework\Assert;

final class GlobalTestInfra
{
    protected ResourcesCleanerHandle $resourcesCleaner;
    protected MockOTelCollectorHandle $mockOTelCollector;

    /** @var int[] */
    private array $portsInUse = [];

    public function __construct()
    {
        $this->resourcesCleaner = $this->startResourcesCleaner();
        $this->mockOTelCollector = $this->startMockOTelCollector($this->resourcesCleaner);
    }

    public function onTestStart(): void
    {
        $this->cleanTestScoped();
    }

    public function onTestEnd(): void
    {
        $this->cleanTestScoped();
    }

    private function cleanTestScoped(): void
    {
        $this->mockOTelCollector->cleanTestScoped();
        $this->resourcesCleaner->cleanTestScoped();
    }

    public function getResourcesCleaner(): ResourcesCleanerHandle
    {
        return $this->resourcesCleaner;
    }

    public function getMockOTelCollector(): MockOTelCollectorHandle
    {
        return $this->mockOTelCollector;
    }

    /**
     * @return int[]
     */
    public function getPortsInUse(): array
    {
        return $this->portsInUse;
    }

    /**
     * @param int[] $ports
     *
     * @return void
     */
    private function addPortsInUse(array $ports): void
    {
        foreach ($ports as $port) {
            Assert::assertNotContains($port, $this->portsInUse);
            $this->portsInUse[] = $port;
        }
    }

    private function startResourcesCleaner(): ResourcesCleanerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            dbgProcessNamePrefix: ClassNameUtil::fqToShort(ResourcesCleaner::class),
            runScriptName: 'runResourcesCleaner.php',
            portsInUse: $this->portsInUse,
            portsToAllocateCount: 1,
            resourcesCleaner: null,
        );
        $this->addPortsInUse($httpServerHandle->ports);
        return new ResourcesCleanerHandle($httpServerHandle);
    }

    private function startMockOTelCollector(ResourcesCleanerHandle $resourcesCleaner): MockOTelCollectorHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            dbgProcessNamePrefix: ClassNameUtil::fqToShort(MockOTelCollector::class),
            runScriptName: 'runMockOTelCollector.php',
            portsInUse: $this->portsInUse,
            portsToAllocateCount: 2,
            resourcesCleaner: $resourcesCleaner,
        );
        $this->addPortsInUse($httpServerHandle->ports);
        return new MockOTelCollectorHandle($httpServerHandle);
    }
}
