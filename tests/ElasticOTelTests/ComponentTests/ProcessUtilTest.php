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

use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticOTelTests\ComponentTests\Util\DbgProcessNameGenerator;
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\ComponentTests\Util\HelperSleepsAndExitsWithArgCode;
use ElasticOTelTests\ComponentTests\Util\InfraUtilForTests;
use ElasticOTelTests\ComponentTests\Util\ProcessUtil;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class ProcessUtilTest extends ComponentTestCaseBase
{
    private const EXIT_CODE = 'exit_code';
    private const SHOULD_WAIT_SUCCEED = 'should_wait_succeed';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestStartAndWaitReturnsCorrectExitCode(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addKeyedDimensionOnlyFirstValueCombinable(self::EXIT_CODE, [123, 231])
                ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_WAIT_SUCCEED)
        );
    }

    /**
     * @dataProvider dataProviderForTestStartAndWaitReturnsCorrectExitCode
     */
    public function testStartAndWaitReturnsCorrectExitCode(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxy = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $testCaseHandle = $this->getTestCaseHandle();
        $exitCode = $testArgs->getInt(self::EXIT_CODE);
        $shouldWaitSucceed = $testArgs->getBool(self::SHOULD_WAIT_SUCCEED);
        if ($shouldWaitSucceed) {
            $helperToSleepSeconds = 0;
            $waitForHelperToExitSecondsInMicroseconds = 100 * 1000_000;
        } else {
            $helperToSleepSeconds = 1000;
            $waitForHelperToExitSecondsInMicroseconds = 1;
        }

        $dbgProcessName = DbgProcessNameGenerator::generate(ClassNameUtil::fqToShort(HelperSleepsAndExitsWithArgCode::class));
        $runHelperScriptFullPath = FileUtil::listToPath([__DIR__, 'Util', 'runHelperSleepsAndExitsWithArgCode.php']);
        $command = "php \"$runHelperScriptFullPath\" $helperToSleepSeconds $exitCode";
        $baseEnvVars = EnvVarUtilForTests::getAll();
        $additionalEnvVars = [
            OptionForProdName::autoload_enabled->toEnvVarName()          => BoolUtil::toString(false),
            OptionForProdName::disabled_instrumentations->toEnvVarName() => ConfigUtilForTests::PROD_DISABLED_INSTRUMENTATIONS_ALL,
            OptionForProdName::enabled->toEnvVarName()                   => BoolUtil::toString(false),
        ];
        ArrayUtilForTests::append(from: $additionalEnvVars, to: $baseEnvVars);

        $envVars = InfraUtilForTests::buildEnvVarsForSpawnedProcessWithoutAppCode(
            $dbgProcessName,
            InfraUtilForTests::generateSpawnedProcessInternalId(),
            [] /* <- ports */,
            $testCaseHandle->getResourcesCleaner(),
        );

        $loggerProxy && $loggerProxy->log(__LINE__, 'Before ProcessUtil::startProcessAndWaitForItToExit');
        $procInfo = ProcessUtil::startProcessAndWaitForItToExit($dbgProcessName, $command, $envVars, $waitForHelperToExitSecondsInMicroseconds);
        $dbgCtx->add(compact('procInfo'));
        $loggerProxy && $loggerProxy->log(__LINE__, 'After ProcessUtil::startProcessAndWaitForItToExit');
        if ($shouldWaitSucceed) {
            self::assertSame($exitCode, $procInfo['exitCode']);
        } else {
            self::assertNull($procInfo['exitCode']);
        }
    }
}
