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

/**
 * PhpUnitExtension is used in phpunit_component_tests.xml
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace ElasticOTelTests\ComponentTests\Util;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\PHPUnitExtensionBase;
use Override;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Referenced in PHPUnit's configuration file - phpunit_component_tests.xml
 */
final class ComponentTestsPHPUnitExtension extends PHPUnitExtensionBase
{
    private readonly Logger $logger;
    private static ?GlobalTestInfra $globalTestInfra = null;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $this->logger->addContext('appCodeHostKind', AmbientContextForTests::testConfig()->appCodeHostKind());

        try {
            // We spin off test infrastructure servers here and not on demand
            // in self::getGlobalTestInfra() because PHPUnit might fork to run individual tests
            // and ResourcesCleaner would track the PHPUnit child process as its master which would be wrong
            self::$globalTestInfra = new GlobalTestInfra();
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwable, 'Throwable escaped from GlobalTestInfra constructor');
            throw $throwable;
        }
    }

    public function __destruct()
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Destroying...');

        self::$globalTestInfra?->getResourcesCleaner()->signalAndWaitForItToExit();
    }

    public static function getGlobalTestInfra(): GlobalTestInfra
    {
        Assert::assertNotNull(self::$globalTestInfra);
        return self::$globalTestInfra;
    }

    #[Override]
    protected function logLevelForEnvInfo(): LogLevel
    {
        return LogLevel::info;
    }
}
