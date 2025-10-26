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

namespace ElasticOTelTests\ComponentTests;

use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class RemoteConfigTest extends ComponentTestCaseBase
{
    // private const MAX_WAIT_FOR_CONFIG_TO_BE_APPLIED_IN_SECONDS = 60; // 1 minute
    // private const SLEEP_BETWEEN_ATTEMPTS_WAITING_FOR_CONFIG_TO_BE_APPLIED_SECONDS = 5;

    private const SERVICE_NAME = 'RemoteConfigTest_service';
    private const ENV_NAME = 'RemoteConfigTest_env';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestLoggingLevel(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionAllValuesCombinable(self::APPLY_CONFIG_TO_SERVICE_KEY, [self::SERVICE_NAME, 'dummy_service', null])
                ->addKeyedDimensionAllValuesCombinable(self::APPLY_CONFIG_TO_ENV_KEY, [self::ENV_NAME, 'dummy_env', null])
        );
    }

    private function implTestLoggingLevel(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($isAutoInstrumentationEnabled): void {
                if (!$isAutoInstrumentationEnabled) {
                    $appCodeParams->setProdOptionIfNotNull(OptionForProdName::disabled_instrumentations, self::AUTO_INSTRUMENTATION_NAME);
                }
                self::disableTimingDependentFeatures($appCodeParams);
            }
        );
    }

    /**
     * @dataProvider dataProviderForTestLoggingLevel
     */
    public function testLoggingLevel(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestLoggingLevel($testArgs);
            }
        );
    }
}
