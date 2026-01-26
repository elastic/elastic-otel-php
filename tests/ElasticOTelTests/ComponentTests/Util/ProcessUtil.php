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

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\OsUtil;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-type PidParentPidCmd array{'pid': int, 'parentPid': int, 'cmd': string}
 * @phpstan-type PidToDbgDesc array<int, string>
 * @phpstan-type PidToParentPid array<int, int>
 *
 * @phpstan-import-type EnvVars from EnvVarUtil
 */
final class ProcessUtil
{
    use StaticClassTrait;

    /**
     * @param array<string> $cmdOutputLines
     *
     * @return list<PidParentPidCmd>
     */
    public static function parseProcessesInfoPsOutput(array $cmdOutputLines): array
    {
        // ps -A -o pid= -o ppid= -o cmd=
        //      ...
        //      2440    2439 -bash
        //      2743    2440 watch docker ps
        //      ...

        $result = [];
        foreach ($cmdOutputLines as $line) {
            // Split by one or more whitespace characters (\s+) and ignore empty results
            $parts = preg_split('/\s+/', $line, limit: 3, flags: PREG_SPLIT_NO_EMPTY);
            Assert::assertCount(3, $parts);
            $pid = AssertEx::stringIsInt($parts[0]);
            Assert::assertArrayNotHasKey($pid, $result);
            $result[] = ['pid' => $pid, 'parentPid' => AssertEx::stringIsInt($parts[1]), 'cmd' => $parts[2]];
        }

        return $result;
    }

    /**
     * @return list<PidParentPidCmd>
     */
    public static function getAllProcessesInfo(): array
    {
        Assert::assertTrue(OsUtil::isWindows());
        $retVal = exec('ps -A -o pid= -o ppid= -o cmd=', $cmdOutput, $cmdExitCode);
        Assert::assertNotFalse($retVal);
        Assert::assertSame(0, $cmdExitCode);
        return self::parseProcessesInfoPsOutput($cmdOutput);
    }

    public static function doesProcessExist(int $pid): bool
    {
        Assert::assertTrue(OsUtil::isWindows());
        $retVal = exec("ps -p $pid", $cmdOutput, $cmdExitCode);
        Assert::assertNotFalse($retVal);
        return $cmdExitCode === 0;
    }

    public static function waitForProcessToExitUsingPid(string $dbgProcessDesc, int $pid, int $maxWaitTimeInMicroseconds): bool
    {
        return (new PollingCheck(
            $dbgProcessDesc . ' process (PID: ' . $pid . ') exited' /* <- dbgDesc */,
            $maxWaitTimeInMicroseconds
        ))->run(
            function () use ($pid): bool {
                return !self::doesProcessExist($pid);
            }
        );
    }

    /**
     * @param resource $procOpenRetVal
     */
    private static function getProcStatus($procOpenRetVal): ProcessStatus
    {
        $procStatus = proc_get_status($procOpenRetVal);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if (!is_array($procStatus)) { // @phpstan-ignore function.alreadyNarrowedType
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('proc_get_status returned value which means an error', compact('procStatus')));
        }

        $isRunning = AssertEx::isBool($procStatus['running']);
        return new ProcessStatus(
            command: AssertEx::isString($procStatus['command']),
            pid: AssertEx::isInt($procStatus['pid']),
            isRunning: $isRunning,
            exitCode: $isRunning ? null : AssertEx::isInt($procStatus['exitcode']),
        );
    }

    /**
     * @param resource $procOpenRetVal
     */
    private static function waitForProcessToExitUsingHandle(string $dbgProcessName, $procOpenRetVal, int $maxWaitTimeInMicroseconds): ProcessStatus
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'maxWaitTimeInMicroseconds'));
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $procStatus = null;
        $waitFinishedSuccessfully = (new PollingCheck(
            $dbgProcessName . ' exited',
            $maxWaitTimeInMicroseconds
        ))->run(
            static function () use ($procOpenRetVal, &$procStatus): bool {
                $procStatus = self::getProcStatus($procOpenRetVal);
                return !$procStatus->isRunning;
            }
        );
        $logger->addAllContext(compact('waitFinishedSuccessfully', 'procStatus'));
        AssertEx::isNotNull($procStatus);

        if ($waitFinishedSuccessfully) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Started process exited');
            AssertEx::isNotNull($procStatus->exitCode);
        } else {
            $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__)?->log('Wait for the started process to exit timed out');
            AssertEx::isNull($procStatus->exitCode);
        }

        return $procStatus;
    }

    public static function terminateProcess(string $dbgProcessDesc, int $pid, bool $gracefullyFirst = true): bool
    {
        $forceVariants = $gracefullyFirst ? [false] : [];
        $forceVariants[] = true;
        foreach ($forceVariants as $force) {
            if (!self::execKillCommand($pid, $force)) {
                return false;
            }
            if (self::waitForProcessToExitUsingPid($dbgProcessDesc, $pid, /* maxWaitTimeInMicroseconds - 10 seconds */ 10 * 1000 * 1000)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param PidToDbgDesc $pidToDbgDesc
     */
    public static function terminateProcessesTrees(string $dbgProcessesSetDesc, array $pidToDbgDesc): void
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessesSetDesc', 'pidToDbgDesc'));
        $logDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $logDebug?->log(__LINE__, 'Terminating spawned processes...');

        // TODO: Sergey Kleyman: Implement: ProcessUtil::terminateProcessesTrees
    }

    /**
     * @param PidToParentPid $pidToParentPid
     */
    public static function orderTopologically(array $pidToParentPid): array
    {
        // TODO: Sergey Kleyman: Implement: ProcessUtil::terminateProcessesTrees
        return [];
    }

    private static function execKillCommand(int $pid, bool $force = true): bool
    {
        Assert::assertFalse(OsUtil::isWindows());

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $retVal = exec('kill ' . ($force ? '-s KILL ' : '') . $pid, /* ref */ $cmdOutput, /* ref */ $cmdExitCode);
        $dbgCtx->add(compact('cmdOutput', 'cmdExitCode', 'retVal'));
        Assert::assertNotFalse($retVal);
        if ($cmdExitCode !== 0) {
            AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->ifDebugLevelEnabled(__LINE__, __FUNCTION__)
                ?->log('cmdExitCode !== 0', compact('cmdOutput', 'cmdExitCode'));
        }
        return $cmdExitCode === 0;
    }

    public static function buildStdErrOutFileFullPath(string $dbgProcessName): ?string
    {
        if (AmbientContextForTests::testConfig()->logsDirectory === null) {
            return null;
        }

        return AmbientContextForTests::testConfig()->logsDirectory . DIRECTORY_SEPARATOR . $dbgProcessName . '_stderr_and_stdout.log';
    }

    private static function addStdErrOutRedirect(string $dbgProcessName, string $command): string
    {
        if (($stdErrOutFilePath = self::buildStdErrOutFileFullPath($dbgProcessName)) === null) {
            return $command;
        }

        $commandForBash = "set -e -o pipefail ; $command 2>&1 | tee \"$stdErrOutFilePath\"";
        return "bash -c \"$commandForBash\"";
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function startBackgroundProcess(string $dbgProcessName, string $command, array $envVars): void
    {
        self::procOpenEx($dbgProcessName, self::addStdErrOutRedirect($dbgProcessName, $command) . '&', $envVars, isBackground: true);
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function startProcessAndWaitForItToExit(string $dbgProcessName, string $command, array $envVars, int $maxWaitTimeInMicroseconds): ProcessStatus
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'command', 'envVars'));

        $procOpenRetVal = self::procOpenEx($dbgProcessName, self::addStdErrOutRedirect($dbgProcessName, $command), $envVars, isBackground: false);
        $logger->addAllContext(compact('procOpenRetVal'));

        $procStatus = self::waitForProcessToExitUsingHandle($dbgProcessName, $procOpenRetVal, $maxWaitTimeInMicroseconds);
        if ($procStatus->exitCode === null) {
            ($loggerProxyWarning = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxyWarning->log('Wait for the started process to exit timed out - terminating the process now', compact('procStatus'));
            self::terminateProcess($dbgProcessName, $procStatus->pid);
        }

        $procCloseRetVal = proc_close($procOpenRetVal);
        $logger->addAllContext(compact('procCloseRetVal'));
        // For older versions of PHP (prior to 8.3.0), calling proc_get_status() after the process had already exited
        // would cause subsequent calls to proc_get_status() or proc_close() to return -1.
        // PHP 8.3.0 and newer: This behavior was corrected.
        // The process's exit code is now cached, and subsequent calls will return the correct, cached value.
        if (PHP_VERSION_ID >= 80300 && $procCloseRetVal === -1) {
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('proc_close returned value which means an error', $logger->getContext()));
        }

        return $procStatus;
    }

    /**
     * @phpstan-param EnvVars $envVars
     *
     * @return resource
     */
    private static function procOpenEx(string $dbgProcessName, string $command, array $envVars, bool $isBackground)
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'command', 'envVars', 'isBackground'));

        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Starting process...');

        $pipes = [];
        $procOpenRetVal = proc_open($command, /* descriptor_spec: */ [], /* ref */ $pipes, /* cwd: */ null, $envVars);
        if ($procOpenRetVal === false) {
            ($loggerProxyError = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxyError->log('Failed to start process');
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Failed to start process', $logger->getContext()));
        }

        ($loggerProxy = $logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Started process');
        return $procOpenRetVal;
    }
}
