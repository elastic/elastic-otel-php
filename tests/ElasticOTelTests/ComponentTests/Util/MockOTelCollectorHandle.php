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
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use PHPUnit\Framework\Assert;

final class MockOTelCollectorHandle extends HttpServerHandle
{
    private readonly Logger $logger;
    private int $nextIntakeDataRequestIndexToFetch = 0;

    public function __construct(HttpServerHandle $httpSpawnedProcessHandle)
    {
        parent::__construct(
            ClassNameUtil::fqToShort(MockOTelCollector::class) /* <- dbgServerDesc */,
            $httpSpawnedProcessHandle->spawnedProcessOsId,
            $httpSpawnedProcessHandle->spawnedProcessInternalId,
            $httpSpawnedProcessHandle->ports
        );

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    public function getPortForAgent(): int
    {
        Assert::assertCount(2, $this->ports);
        return $this->ports[1];
    }

    /**
     * @return list<AgentBackendCommEvent>
     */
    public function fetchNewAgentBackendCommEvents(bool $shouldWait): array
    {
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Starting...');

        $response = $this->sendRequest(
            HttpMethods::GET,
            MockOTelCollector::MOCK_API_URI_PREFIX . MockOTelCollector::GET_AGENT_BACKEND_COMM_EVENTS_URI_SUBPATH,
            [
                MockOTelCollector::FROM_INDEX_HEADER_NAME => strval($this->nextIntakeDataRequestIndexToFetch),
                MockOTelCollector::SHOULD_WAIT_HEADER_NAME => BoolUtil::toString($shouldWait),
            ]
        );

        $newEvents = MockOTelCollector::decodeGetAgentBackendCommEvents($response);

        if (ArrayUtilForTests::isEmpty($newEvents)) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Fetched NO new data from agent receiver events');
        } else {
            $this->nextIntakeDataRequestIndexToFetch += count($newEvents);
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Fetched new data from agent receiver events', ['count(newEvents)' => count($newEvents)]);
        }
        return $newEvents;
    }

    public function cleanTestScoped(): void
    {
        $this->nextIntakeDataRequestIndexToFetch = 0;

        $response = $this->sendRequest(HttpMethods::POST, TestInfraHttpServerProcessBase::CLEAN_TEST_SCOPED_URI_PATH);
        Assert::assertSame(HttpStatusCodes::OK, $response->getStatusCode());
    }
}
