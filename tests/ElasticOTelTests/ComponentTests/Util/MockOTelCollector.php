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
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Clock;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;

final class MockOTelCollector extends TestInfraHttpServerProcessBase
{
    use HttpServerProcessTrait;

    public const TESTS_INFRA_PORT_INDEX = 0;
    public const SIGNAL_INTAKE_PORT_INDEX = 1;
    public const OPAMP_PORT_INDEX = 2;

    public readonly Clock $clock;

    private readonly MockOTelCollectorSignalIntake $signalIntakeModule;
    public readonly MockOTelCollectorOpAMP $opampModule;
    public readonly MockOTelCollectorTestsInfra $testsInfraModule;

    private readonly Logger $logger;

    public function __construct()
    {
        parent::__construct();

        $this->clock = new Clock(AmbientContextForTests::loggerFactory());

        $this->signalIntakeModule = new MockOTelCollectorSignalIntake($this);
        $this->opampModule = new MockOTelCollectorOpAMP($this);
        $this->testsInfraModule = new MockOTelCollectorTestsInfra($this);

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
    }

    #[Override]
    public static function portsCount(): int
    {
        return 3;
    }

    #[Override]
    protected static function dbgPortDesc(int $portIndex): string
    {
        return match ($portIndex) {
            self::TESTS_INFRA_PORT_INDEX => 'tests infra',
            self::SIGNAL_INTAKE_PORT_INDEX => 'signal data intake',
            self::OPAMP_PORT_INDEX => 'OpAMP',
            default => Assert::fail("Unexpected port index $portIndex")
        };
    }

    public static function getPortByIndex(int $portIndex): int
    {
        return AmbientContextForTests::testConfig()->dataPerProcess()->thisServerPorts[$portIndex];
    }

    #[Override]
    protected function isTestsInfraRequest(int $portIndex): bool
    {
        return $portIndex === self::TESTS_INFRA_PORT_INDEX;
    }

    #[Override]
    protected function onNewConnection(int $portIndex, ConnectionInterface $connection): void
    {
        parent::onNewConnection($portIndex, $connection);

        if ($portIndex !== self::TESTS_INFRA_PORT_INDEX) {
            $this->testsInfraModule->addAgentBackendCommEvent(
                new AgentBackendConnectionStarted(self::getPortByIndex($portIndex), $this->clock->getMonotonicClockCurrentTime(), $this->clock->getSystemClockCurrentTime())
            );
        }
    }

    #[Override]
    protected function processRequest(int $portIndex, ServerRequestInterface $request): null|ResponseInterface|Promise
    {
        $module = match ($portIndex) {
            self::TESTS_INFRA_PORT_INDEX => $this->testsInfraModule,
            self::SIGNAL_INTAKE_PORT_INDEX => $this->signalIntakeModule,
            self::OPAMP_PORT_INDEX => $this->opampModule,
            default => Assert::fail("Unexpected port index $portIndex")
        };

        return $module->processRequest($request);
    }

    public function cleanTestScoped(): void
    {
        $beforeClean = MemoryUtil::logMemoryUsage('Before cleaning test scoped');

        $this->opampModule->cleanTestScoped();
        $this->testsInfraModule->cleanTestScoped();

        MemoryUtil::logMemoryUsage('After cleaning test scoped', $beforeClean);

        $collectedCyclesCount = gc_collect_cycles();
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log("gc_collect_cycles() returned $collectedCyclesCount");
        MemoryUtil::logMemoryUsage('After calling gc_collect_cycles()', $beforeClean);
    }

    public function reactLoop(): LoopInterface
    {
        return AssertEx::notNull($this->reactLoop);
    }

    #[Override]
    public function exit(): void
    {
        $this->testsInfraModule->exit();

        parent::exit();
    }
}
