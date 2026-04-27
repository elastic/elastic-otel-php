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

/** @noinspection PhpUnitMisorderedAssertEqualsArgumentsInspection */

declare(strict_types=1);

namespace ElasticOTelTests\UnitTests;

use Elastic\OTel\Config\OTelConfigOptionValues;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForProdDefaultValues;
use ElasticOTelTests\Util\ReflectionUtil;
use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\API\Behavior\Internal\Logging as OTelInternalLogging;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTelSdkConfigVariables;
use OpenTelemetry\SDK\Sdk as OTelSdk;

final class EdotDependenciesOnOTelSdkTest extends TestCaseBase
{
    public function testOTelLogLevelOptionNameInSync(): void
    {
        /**
         * @see \OpenTelemetry\API\Behavior\Internal\Logging::getLogLevel
         * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
         */
        $envVarNameActuallyUsedByOTelForLogLevel = ReflectionUtil::getConstValue(OTelInternalLogging::class, 'OTEL_LOG_LEVEL');
        self::assertSame($envVarNameActuallyUsedByOTelForLogLevel, OTelSdkConfigVariables::OTEL_LOG_LEVEL);
        self::assertSame($envVarNameActuallyUsedByOTelForLogLevel, OptionForProdName::log_level->toEnvVarName());

        self::assertSame(ReflectionUtil::getConstValue(OTelInternalLogging::class, 'DEFAULT_LEVEL'), OptionsForProdDefaultValues::LOG_LEVEL->name);

        /**
         * Also regarding verification of OTel log level values being in sync
         * @see \ElasticOTelTests\UnitTests\UtilTests\LogTests\OTelInternalLogLevelTest
         */
    }

    public function testDeactivateAllInstrumentationsRelatedNames(): void
    {
        self::assertSame(ReflectionUtil::getConstValue(OTelSdk::class, 'OTEL_PHP_DISABLED_INSTRUMENTATIONS_ALL'), OTelConfigOptionValues::DISABLED_INSTRUMENTATIONS_ALL);
    }
}
