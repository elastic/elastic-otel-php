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
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;

final class ResourcesClient
{
    private Logger $logger;

    public function __construct(
        private readonly string $resourcesCleanerSpawnedProcessInternalId,
        private readonly int $resourcesCleanerPort
    ) {
        $this->logger = $this->buildLogger();
    }

    public function buildLogger(): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /**
     * @return list<string>
     */
    public function __sleep(): array
    {
        $result = [];
        /** @var string $propName */
        foreach ($this as $propName => $_) { // @phpstan-ignore foreach.nonIterable
            if ($propName === 'logger') {
                continue;
            }
            $result[] = $propName;
        }
        return $result;
    }

    public function __wakeup(): void
    {
        $this->logger = $this->buildLogger();
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function registerFileToDelete(string $fullPath, bool $isTestScoped): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Registering file to delete with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class), compact('fullPath'));

        $response = HttpClientUtilForTests::sendRequest(
            HttpMethods::POST,
            new UrlParts(port: $this->resourcesCleanerPort, path: ResourcesCleaner::REGISTER_FILE_TO_DELETE_URI_PATH),
            new TestInfraDataPerRequest(spawnedProcessInternalId: $this->resourcesCleanerSpawnedProcessInternalId),
            [ResourcesCleaner::PATH_HEADER_NAME => $fullPath, ResourcesCleaner::IS_TEST_SCOPED_HEADER_NAME => BoolUtil::toString($isTestScoped)] /* <- headers */
        );
        if ($response->getStatusCode() !== HttpStatusCodes::OK) {
            throw new ComponentTestsInfraException('Failed to register with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class));
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully registered file to delete with ' . ClassNameUtil::fqToShort(ResourcesCleaner::class), compact('fullPath'));
    }

    public function createTempFile(?string $dbgTempFilePurpose = null, bool $shouldBeDeletedOnTestExit = true): string
    {
        $tempFileFullPath = FileUtil::createTempFile($dbgTempFilePurpose);
        if ($shouldBeDeletedOnTestExit) {
            $this->registerFileToDelete($tempFileFullPath, isTestScoped: true);
        }
        return $tempFileFullPath;
    }
}
