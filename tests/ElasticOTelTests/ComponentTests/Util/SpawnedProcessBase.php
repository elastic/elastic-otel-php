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

use Closure;
use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LoggingSubsystem;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 */
abstract class SpawnedProcessBase implements LoggableInterface
{
    use LoggableTrait;

    public const FAILURE_PROCESS_EXIT_CODE = 213;
    public const DBG_PROCESS_NAME_ENV_VAR_NAME = 'ELASTIC_OTEL_PHP_TESTS_DBG_PROCESS_NAME';

    private readonly Logger $logger;

    protected function __construct()
    {
        $this->logger = self::buildLogger()->addAllContext(compact('this'));

        ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Finishing constructor...', ['test config' => AmbientContextForTests::testConfig(), 'environment variables' => EnvVarUtilForTests::getAll()]);
    }

    private static function buildLogger(): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
    }

    protected function processConfig(): void
    {
        AmbientContextForTests::testConfig()->validateForSpawnedProcess();

        if ($this->shouldRegisterThisProcessWithResourcesCleaner()) {
            Assert::assertNotNull(
                AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerSpawnedProcessInternalId,
                LoggableToString::convert(AmbientContextForTests::testConfig())
            );
            Assert::assertNotNull(
                AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerPort,
                LoggableToString::convert(AmbientContextForTests::testConfig())
            );
        }
    }

    /**
     * @param Closure(SpawnedProcessBase): void $runImpl
     *
     * @throws Throwable
     */
    protected static function runSkeleton(Closure $runImpl): void
    {
        LoggingSubsystem::$isInTestingContext = true;

        try {
            $dbgProcessName = EnvVarUtilForTests::get(self::DBG_PROCESS_NAME_ENV_VAR_NAME);
            Assert::assertIsString($dbgProcessName);

            AmbientContextForTests::init($dbgProcessName);

            $thisObj = new static(); // @phpstan-ignore new.static

            if (!$thisObj->shouldTracingBeEnabled()) {
                ConfigUtilForTests::verifyTracingIsDisabled();
            }

            $thisObj->processConfig();

            if ($thisObj->shouldRegisterThisProcessWithResourcesCleaner()) {
                $resourcesClient = new ResourcesClient(
                    AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerSpawnedProcessInternalId,
                    AmbientContextForTests::testConfig()->dataPerProcess()->resourcesCleanerPort,
                );
                $resourcesClient->registerProcessToTerminate(AmbientContextForTests::dbgProcessName(), $thisObj->isThisProcessTestScoped());
            }

            $runImpl($thisObj);
        } catch (Throwable $throwable) {
            $level = LogLevel::critical;
            $isExpectedFromAppCode = false;
            $throwableToLog = $throwable;
            if ($throwable instanceof WrappedAppCodeException) {
                $isExpectedFromAppCode = true;
                $level = LogLevel::info;
                $throwableToLog = $throwable->wrappedException();
            }
            $logger = isset($thisObj) ? $thisObj->logger : self::buildLogger();
            ($loggerProxy = $logger->ifLevelEnabled($level, __LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwableToLog, 'Throwable escaped to the top of the script', compact('isExpectedFromAppCode'));
            if ($isExpectedFromAppCode) {
                /** @noinspection PhpUnhandledExceptionInspection */
                throw $throwableToLog;
            } else {
                exit(self::FAILURE_PROCESS_EXIT_CODE);
            }
        }
    }

    protected function shouldTracingBeEnabled(): bool
    {
        return false;
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return true;
    }

    protected function isThisProcessTestScoped(): bool
    {
        return false;
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function startProcessAndWaitForItToExit(string $dbgProcessName, string $command, array $envVars): void
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'command', 'envVars'));

        $procStatus = ProcessUtil::startProcessAndWaitForItToExit($dbgProcessName, $command, $envVars, /* maxWaitTimeInMicroseconds - 30 seconds */ 30 * 1000 * 1000);
        $logger->addAllContext(compact('procStatus'));

        if ($procStatus->exitCode === SpawnedProcessBase::FAILURE_PROCESS_EXIT_CODE) {
            ($loggerProxyError = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxyError->log('Process exited with the failure exit code');
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Process exited with the failure exit code', $logger->getContext()));
        }
    }
}
