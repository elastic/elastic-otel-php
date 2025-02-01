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

use Ds\Set;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\TestCaseBase;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\TimerInterface;

final class ResourcesCleaner extends TestInfraHttpServerProcessBase
{
    public const REGISTER_PROCESS_TO_TERMINATE_URI_PATH = TestInfraHttpServerProcessBase::BASE_URI_PATH . 'register_process_to_terminate';
    public const REGISTER_FILE_TO_DELETE_URI_PATH = TestInfraHttpServerProcessBase::BASE_URI_PATH . 'register_file_to_delete';

    public const PID_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PID';
    public const IS_TEST_SCOPED_QUERY_HEADER_NAME
        = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'IS_TEST_SCOPED';
    public const PATH_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PATH';

    /** @var Set<string> */
    private Set $globalFilesToDeletePaths;

    /** @var Set<string> */
    private Set $testScopedFilesToDeletePaths;

    /** @var Set<int> */
    private Set $globalProcessesToTerminateIds;

    /** @var Set<int> */
    private Set $testScopedProcessesToTerminateIds;

    private ?TimerInterface $parentProcessTrackingTimer = null;

    private Logger $logger;

    public function __construct()
    {
        $this->globalFilesToDeletePaths = new Set();
        $this->testScopedFilesToDeletePaths = new Set();

        $this->globalProcessesToTerminateIds = new Set();
        $this->testScopedProcessesToTerminateIds = new Set();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        parent::__construct();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Done');
    }

    #[Override]
    protected function beforeLoopRun(): void
    {
        parent::beforeLoopRun();

        TestCaseBase::assertNotNull($this->reactLoop);
        $this->parentProcessTrackingTimer = $this->reactLoop->addPeriodicTimer(
            1 /* interval in seconds */,
            function () {
                $rootProcessId = AmbientContextForTests::testConfig()->dataPerProcess()->rootProcessId;
                if (!ProcessUtil::doesProcessExist($rootProcessId)) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Detected that parent process does not exist');
                    $this->exit();
                }
            }
        );
    }

    #[Override]
    protected function exit(): void
    {
        $this->cleanSpawnedProcesses(isTestScopedOnly: false);
        $this->cleanFiles(isTestScopedOnly: false);

        TestCaseBase::assertNotNull($this->reactLoop);
        TestCaseBase::assertNotNull($this->parentProcessTrackingTimer);
        $this->reactLoop->cancelTimer($this->parentProcessTrackingTimer);

        parent::exit();
    }

    private function cleanSpawnedProcesses(bool $isTestScopedOnly): void
    {
        $this->cleanSpawnedProcessesFrom(/* dbgFilesSetDesc */ 'test scoped', $this->testScopedProcessesToTerminateIds);
        if (!$isTestScopedOnly) {
            $this->cleanSpawnedProcessesFrom(/* dbgFilesSetDesc */ 'global', $this->globalProcessesToTerminateIds);
        }
    }

    private function cleanTestScoped(): void
    {
        $this->cleanSpawnedProcesses(isTestScopedOnly: true);
        $this->cleanFiles(isTestScopedOnly: true);
    }

    /**
     * @param Set<int> $processesToTerminateIds
     */
    private function cleanSpawnedProcessesFrom(string $dbgProcessSetDesc, Set $processesToTerminateIds): void
    {
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $processesToTerminateIdsCount = $processesToTerminateIds->count();
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Terminating spawned processes ()...', compact('dbgProcessSetDesc', 'processesToTerminateIdsCount'));

        foreach ($processesToTerminateIds as $spawnedProcessesId) {
            if (!ProcessUtil::doesProcessExist($spawnedProcessesId)) {
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Spawned process does not exist anymore - no need to terminate', compact('spawnedProcessesId'));
                continue;
            }
            $hasExitedNormally = ProcessUtil::terminateProcess($spawnedProcessesId);
            $hasExited = ProcessUtil::waitForProcessToExit(/* dbgProcessDesc: */ 'Spawned', $spawnedProcessesId, /* maxWaitTimeInMicroseconds = 10 seconds */ 10 * 1000 * 1000);
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Issued command to terminate spawned process', compact('spawnedProcessesId', 'hasExited', 'hasExitedNormally'));
        }

        $processesToTerminateIds->clear();
    }

    private function cleanFiles(bool $isTestScopedOnly): void
    {
        $this->cleanFilesFrom(/* dbgFilesSetDesc */ 'test scoped', $this->testScopedFilesToDeletePaths);
        if (!$isTestScopedOnly) {
            $this->cleanFilesFrom(/* dbgFilesSetDesc */ 'global', $this->globalFilesToDeletePaths);
        }
    }

    /**
     * @param Set<string> $filesToDeletePaths
     */
    private function cleanFilesFrom(string $dbgFilesSetDesc, Set $filesToDeletePaths): void
    {
        $filesToDeletePathsCount = $filesToDeletePaths->count();
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Deleting files...', compact('dbgFilesSetDesc', 'filesToDeletePathsCount'));

        foreach ($filesToDeletePaths as $fileToDeletePath) {
            if (!file_exists($fileToDeletePath)) {
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'File does not exist - so there is nothing to delete', compact('fileToDeletePath'));
                continue;
            }

            $unlinkRetVal = unlink($fileToDeletePath);
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Called unlink() to delete file', compact('fileToDeletePath', 'unlinkRetVal'));
        }

        $filesToDeletePaths->clear();
    }

    /** @inheritDoc */
    #[Override]
    protected function processRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        switch ($request->getUri()->getPath()) {
            case self::REGISTER_PROCESS_TO_TERMINATE_URI_PATH:
                $this->registerProcessToTerminate($request);
                break;
            case self::REGISTER_FILE_TO_DELETE_URI_PATH:
                $this->registerFileToDelete($request);
                break;
            case self::CLEAN_TEST_SCOPED_URI_PATH:
                $this->cleanTestScoped();
                break;
            default:
                return null;
        }
        return self::buildDefaultResponse();
    }

    protected function registerProcessToTerminate(ServerRequestInterface $request): void
    {
        $pid = intval(self::getRequiredRequestHeader($request, self::PID_QUERY_HEADER_NAME));
        $isTestScopedAsString = self::getRequiredRequestHeader($request, self::IS_TEST_SCOPED_QUERY_HEADER_NAME);
        $isTestScoped = JsonUtil::decode($isTestScopedAsString, asAssocArray: true);
        $processesToTerminateIds = $isTestScoped ? $this->testScopedProcessesToTerminateIds : $this->globalProcessesToTerminateIds;
        $processesToTerminateIds->add($pid);
        $processesToTerminateIdsCount = $processesToTerminateIds->count();
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully registered process to terminate', compact('pid', 'isTestScoped', 'processesToTerminateIdsCount'));
    }

    protected function registerFileToDelete(ServerRequestInterface $request): void
    {
        $path = self::getRequiredRequestHeader($request, self::PATH_QUERY_HEADER_NAME);
        $isTestScopedAsString = self::getRequiredRequestHeader($request, self::IS_TEST_SCOPED_QUERY_HEADER_NAME);
        $isTestScoped = JsonUtil::decode($isTestScopedAsString, asAssocArray: true);
        $filesToDeletePaths = $isTestScoped ? $this->testScopedFilesToDeletePaths : $this->globalFilesToDeletePaths;
        $filesToDeletePaths->add($path);
        $filesToDeletePathsCount = $filesToDeletePaths->count();
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Successfully registered file to delete', compact('path', 'isTestScoped', 'filesToDeletePathsCount'));
    }

    #[Override]
    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return false;
    }
}
