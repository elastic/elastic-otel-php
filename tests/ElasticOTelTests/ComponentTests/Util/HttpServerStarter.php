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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\RandomUtil;
use ElasticOTelTests\Util\RangeUtil;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 */
abstract class HttpServerStarter
{
    use LoggableTrait;

    private const PORTS_RANGE_BEGIN = 50000;
    public const PORTS_RANGE_END = 60000;

    private const MAX_WAIT_SERVER_START_MICROSECONDS = 10 * 1000 * 1000; // 10 seconds
    private const MAX_TRIES_TO_START_SERVER = 3;

    private readonly Logger $logger;

    protected function __construct(
        protected readonly string $dbgProcessNamePrefix
    ) {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /**
     * @param int[] $ports
     */
    abstract protected function buildCommandLine(array $ports): string;

    /**
     * @param int[] $ports
     *
     * @return EnvVars
     */
    abstract protected function buildEnvVarsForSpawnedProcess(string $dbgProcessName, string $spawnedProcessInternalId, array $ports): array;

    /**
     * @param int[] $portsInUse
     * @param int   $portsToAllocateCount
     *
     * @return HttpServerHandle
     */
    protected function startHttpServer(array $portsInUse, int $portsToAllocateCount = 1): HttpServerHandle
    {
        Assert::assertGreaterThanOrEqual(1, $portsToAllocateCount);
        /** @var ?int $lastTriedPort */
        $lastTriedPort = ArrayUtilForTests::isEmpty($portsInUse) ? null : ArrayUtilForTests::getLastValue($portsInUse);
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            $dbgProcessName = DbgProcessNameGenerator::generate($this->dbgProcessNamePrefix);
            /** @var int[] $currentTryPorts */
            $currentTryPorts = [];
            self::findFreePortsToListen($portsInUse, $portsToAllocateCount, $lastTriedPort, /* out */ $currentTryPorts);
            Assert::assertSame($portsToAllocateCount, count($currentTryPorts));
            /**
             * We repeat $currentTryPorts type to fix PHPStan's
             * "Unable to resolve the template type T in call to method static method" error
             *
             * @var int[] $currentTryPorts
             * @noinspection PhpRedundantVariableDocTypeInspection
             */
            $lastTriedPort = ArrayUtilForTests::getLastValue($currentTryPorts);
            $currentTrySpawnedProcessInternalId = InfraUtilForTests::generateSpawnedProcessInternalId();
            $cmdLine = $this->buildCommandLine($currentTryPorts);
            $envVars = $this->buildEnvVarsForSpawnedProcess($dbgProcessName, $currentTrySpawnedProcessInternalId, $currentTryPorts);

            $logger = $this->logger->inherit()->addAllContext(
                array_merge(
                    ['dbgProcessName' => $dbgProcessName, 'maxTries' => self::MAX_TRIES_TO_START_SERVER],
                    compact('tryCount', 'currentTryPorts', 'currentTrySpawnedProcessInternalId', 'cmdLine', 'envVars')
                )
            );
            $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Starting HTTP server...');
            ProcessUtil::startBackgroundProcess($dbgProcessName, $cmdLine, $envVars);

            $pid = -1;
            if ($this->isHttpServerRunning($dbgProcessName, $currentTrySpawnedProcessInternalId, $currentTryPorts[0], $logger, /* ref */ $pid)) {
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Started HTTP server', compact('pid'));
                return new HttpServerHandle($dbgProcessName, $pid, $currentTrySpawnedProcessInternalId, $currentTryPorts);
            }

            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Failed to start HTTP server');
        }

        throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Failed to start HTTP server', ['dbgProcessNamePrefix' => $this->dbgProcessNamePrefix]));
    }

    /**
     * @param int[]  $portsInUse
     * @param ?int   $lastTriedPort
     * @param int    $portsToFindCount
     * @param int[] &$result
     *
     * @return void
     */
    private static function findFreePortsToListen(
        array $portsInUse,
        int $portsToFindCount,
        ?int $lastTriedPort,
        array &$result
    ): void {
        $result = [];
        $lastTriedPortLocal = $lastTriedPort;
        foreach (RangeUtil::generateUpTo($portsToFindCount) as $ignored) {
            $foundPort = self::findFreePortToListen($portsInUse, $lastTriedPortLocal);
            $result[] = $foundPort;
            $lastTriedPortLocal = $foundPort;
        }
    }

    /**
     * @param int[] $portsInUse
     * @param ?int  $lastTriedPort
     *
     * @return int
     */
    private static function findFreePortToListen(array $portsInUse, ?int $lastTriedPort): int
    {
        $calcNextInCircularPortRange = function (int $port): int {
            return $port === (self::PORTS_RANGE_END - 1) ? self::PORTS_RANGE_BEGIN : ($port + 1);
        };

        $portToStartSearchFrom = $lastTriedPort === null
            ? RandomUtil::generateIntInRange(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1)
            : $calcNextInCircularPortRange($lastTriedPort);
        $candidate = $portToStartSearchFrom;
        while (true) {
            if (!in_array($candidate, $portsInUse)) {
                break;
            }
            $candidate = $calcNextInCircularPortRange($candidate);
            if ($candidate === $portToStartSearchFrom) {
                TestCase::fail(
                    'Could not find a free port'
                    . LoggableToString::convert(
                        [
                            'portsInUse' => $portsInUse,
                            'portToStartSearchFrom' => $portToStartSearchFrom,
                        ]
                    )
                );
            }
        }
        return $candidate;
    }

    private function isHttpServerRunning(string $dbgProcessName, string $spawnedProcessInternalId, int $port, Logger $logger, int &$pid): bool
    {
        /** @var ?Throwable $lastThrown */
        $lastThrown = null;
        $dataPerRequest = new TestInfraDataPerRequest(spawnedProcessInternalId: $spawnedProcessInternalId);
        $checkResult = (new PollingCheck(
            $dbgProcessName . ' started',
            self::MAX_WAIT_SERVER_START_MICROSECONDS
        ))->run(
            function () use ($port, $dataPerRequest, $logger, &$lastThrown, &$pid) {
                try {
                    $response = HttpClientUtilForTests::sendRequest(
                        HttpMethods::GET,
                        new UrlParts(host: HttpServerHandle::CLIENT_LOCALHOST_ADDRESS, port: $port, path: HttpServerHandle::STATUS_CHECK_URI_PATH),
                        $dataPerRequest
                    );
                } catch (Throwable $throwable) {
                    ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->logThrowable($throwable, 'Caught while checking if HTTP server is running');
                    $lastThrown = $throwable;
                    return false;
                }

                if ($response->getStatusCode() !== HttpStatusCodes::OK) {
                    ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Received non-OK status code in response to status check',
                        ['receivedStatusCode' => $response->getStatusCode()]
                    );
                    return false;
                }

                /** @var array<string, mixed> $decodedBody */
                $decodedBody = JsonUtil::decode($response->getBody()->getContents(), asAssocArray: true);
                TestCase::assertArrayHasKey(HttpServerHandle::PID_KEY, $decodedBody);
                $receivedPid = $decodedBody[HttpServerHandle::PID_KEY];
                TestCase::assertIsInt($receivedPid, LoggableToString::convert(['$decodedBody' => $decodedBody]));
                $pid = $receivedPid;

                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('HTTP server status is OK', ['PID' => $pid]);
                return true;
            }
        );

        if (!$checkResult) {
            if ($lastThrown === null) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to send request to check HTTP server status');
            } else {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->logThrowable($lastThrown, 'Failed to send request to check HTTP server status');
            }
        }

        return $checkResult;
    }
}
