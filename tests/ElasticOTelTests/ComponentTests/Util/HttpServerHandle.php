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

use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class HttpServerHandle implements LoggableInterface
{
    use LoggableTrait;

    public const CLIENT_LOCALHOST_ADDRESS = '127.0.0.1';
    public const SERVER_LOCALHOST_ADDRESS = self::CLIENT_LOCALHOST_ADDRESS;
    public const STATUS_CHECK_URI_PATH = TestInfraHttpServerProcessBase::BASE_URI_PATH . 'status_check';
    public const PID_KEY = 'pid';

    /**
     * @param int[] $ports
     */
    public function __construct(
        public readonly string $dbgProcessName,
        public readonly int $spawnedProcessOsId,
        public readonly string $spawnedProcessInternalId,
        public readonly array $ports
    ) {
    }

    public function getMainPort(): int
    {
        Assert::assertNotEmpty($this->ports);
        return $this->ports[0];
    }

    /**
     * @param array<string, string> $headers
     */
    public function sendRequest(string $httpMethod, string $path, array $headers = []): ResponseInterface
    {
        return HttpClientUtilForTests::sendRequest(
            $httpMethod,
            new UrlParts(port: $this->getMainPort(), path: $path),
            new TestInfraDataPerRequest(spawnedProcessInternalId: $this->spawnedProcessInternalId),
            $headers
        );
    }

    public function signalAndWaitForItToExit(): void
    {
        $response = $this->sendRequest(HttpMethods::POST, TestInfraHttpServerProcessBase::EXIT_URI_PATH);
        Assert::assertSame(HttpStatusCodes::OK, $response->getStatusCode());

        $hasExited = ProcessUtil::waitForProcessToExitUsingPid($this->dbgProcessName, $this->spawnedProcessOsId, /* maxWaitTimeInMicroseconds - 10 seconds */ 10 * 1000 * 1000);
        Assert::assertTrue($hasExited);
    }
}
