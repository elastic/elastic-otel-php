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

use Elastic\OTel\PhpPartFacade;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ElasticOTelExtensionUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\MixedMap;
use Override;
use PHPUnit\Framework\Assert;
use Throwable;

abstract class AppCodeHostBase extends SpawnedProcessBase
{
    private readonly Logger $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Done');
    }

    #[Override]
    protected function shouldTracingBeEnabled(): bool
    {
        return true;
    }

    #[Override]
    protected function processConfig(): void
    {
        parent::processConfig();
        AmbientContextForTests::testConfig()->validateForAppCode();
    }

    abstract protected function runImpl(): void;

    public static function run(): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObj): void {
                Assert::assertInstanceOf(self::class, $thisObj);
                if (!ElasticOTelExtensionUtil::isLoaded()) {
                    throw new ComponentTestsInfraException(
                        'Environment hosting component tests application code should have '
                        . ElasticOTelExtensionUtil::EXTENSION_NAME . ' extension loaded.'
                        . ' php_ini_loaded_file(): ' . php_ini_loaded_file() . '.'
                    );
                }
                if (!PhpPartFacade::$wasBootstrapCalled) {
                    throw new ComponentTestsInfraException('PhpPartFacade::$wasBootstrapCalled is false while it should be true for the process with app code');
                }

                AmbientContextForTests::testConfig()->validateForAppCodeRequest();

                $thisObj->runImpl();
            }
        );
    }

    #[Override]
    protected function isThisProcessTestScoped(): bool
    {
        return true;
    }

    protected function callAppCode(): void
    {
        $dataPerRequest = AmbientContextForTests::testConfig()->dataPerRequest();
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Calling application code...', compact('dataPerRequest'));

        $msg = LoggableToString::convert(AmbientContextForTests::testConfig());
        $appCodeTarget = $dataPerRequest->appCodeTarget;
        Assert::assertNotNull($appCodeTarget, $msg);
        Assert::assertNotNull($appCodeTarget->appCodeClass, $msg);
        Assert::assertNotNull($appCodeTarget->appCodeMethod, $msg);

        try {
            $methodToCall = [$appCodeTarget->appCodeClass, $appCodeTarget->appCodeMethod];
            Assert::assertIsCallable($methodToCall, $msg);
            $appCodeArguments = $dataPerRequest->appCodeArguments;
            if ($appCodeArguments === null) {
                call_user_func($methodToCall);
            } else {
                call_user_func($methodToCall, new MixedMap($appCodeArguments));
            }
        } catch (Throwable $throwable) {
            $loggerProxy = ($dataPerRequest->isAppCodeExpectedToThrow) ? $loggerProxyDebug : $this->logger->ifCriticalLevelEnabledNoLine(__FUNCTION__);
            $loggerProxy && $loggerProxy->logThrowable(__LINE__, $throwable, 'Call to application code exited by exception');
            throw $dataPerRequest->isAppCodeExpectedToThrow ? new WrappedAppCodeException($throwable) : $throwable;
        }

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Call to application code completed');
    }
}
