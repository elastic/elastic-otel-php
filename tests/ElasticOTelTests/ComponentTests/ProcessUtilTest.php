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

use Elastic\OTel\Util\BoolUtil;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticOTelTests\ComponentTests\Util\DbgProcessNameGenerator;
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\ComponentTests\Util\HelperSleepsAndExitsWithArgCode;
use ElasticOTelTests\ComponentTests\Util\InfraUtilForTests;
use ElasticOTelTests\ComponentTests\Util\ProcessUtil;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\OsUtil;

/**
 * @group smoke
 * @group does_not_require_external_services
 *
 * @phpstan-import-type PidToParentPid from ProcessUtil
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
        $runHelperScriptFullPath = FileUtil::partsToPath(__DIR__, 'Util', 'runHelperSleepsAndExitsWithArgCode.php');
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
        $procStatus = ProcessUtil::startProcessAndWaitForItToExit($dbgProcessName, $command, $envVars, $waitForHelperToExitSecondsInMicroseconds);
        $dbgCtx->add(compact('procStatus'));
        $loggerProxy && $loggerProxy->log(__LINE__, 'After ProcessUtil::startProcessAndWaitForItToExit');
        if ($shouldWaitSucceed) {
            self::assertSame($exitCode, $procStatus->exitCode);
        } else {
            self::assertNull($procStatus->exitCode);
        }
    }

    public static function testParseProcessesInfoPsOutput(): void
    {
        // ps -A -o pid= -o ppid= -o cmd=
        //      ...
        //      2440    2439 -bash
        //      2743    2440 watch docker ps
        //      ...

        $psCmdOutputLines = [
            '2440 2439 -bash',
            "2743 \t 2440 \t watch docker \t ps",
        ];
        $expectedResult = [
            ['pid' => 2440, 'parentPid' => 2439, 'cmd' => '-bash'],
            ['pid' => 2743, 'parentPid' => 2440, 'cmd' => "watch docker \t ps"],
        ];
        $actualParseResult = ProcessUtil::parseProcessesInfoPsOutput($psCmdOutputLines);
        self::assertCount(count($expectedResult), $actualParseResult);
        foreach (IterableUtil::zip($expectedResult, $actualParseResult) as [$expectedParsedLine, $actualParsedLine]) {
            AssertEx::arraysHaveTheSameContent($expectedParsedLine, $actualParsedLine);
        }
    }

    public static function testOrderTopologically(): void
    {
        $testImpl = function (array $pidToParentPid): void {

        };

    }

    public static function testDoesProcessExist(): void
    {
        if (OsUtil::isWindows()) {
            self::dummyAssert();
            return;
        }

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $currentPid = getmypid();
        $allProcessesInfo = ProcessUtil::getAllProcessesInfo();
        $dbgCtx->add(compact('currentPid', 'allProcessesInfo'));
        self::assertTrue(ProcessUtil::doesProcessExist($currentPid));

        $maxPid = array_reduce(array_keys($allProcessesInfo), fn($maxPid, $currPid) => $maxPid === null ? max($maxPid, $currPid) : $currPid);
        self::assertFalse(ProcessUtil::doesProcessExist($maxPid + 1));
    }
}
