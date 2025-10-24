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
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 *
 * @phpstan-type ProcessInfo array{'pid': int, 'exitCode': ?int}
 */
final class ProcessUtil
{
    use StaticClassTrait;

    public static function doesProcessExist(int $pid): bool
    {
        exec("ps -p $pid", $cmdOutput, $cmdExitCode);
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
     * @param string $dbgProcessName
     * @param resource $procOpenRetVal
     * @param int $maxWaitTimeInMicroseconds
     *
     * @return ProcessInfo
     */
    private static function waitForProcessToExitUsingHandle(string $dbgProcessName, $procOpenRetVal, int $maxWaitTimeInMicroseconds): array
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'maxWaitTimeInMicroseconds'));
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $pid = null;
        $exitCode = null;
        $waitFinishedSuccessfully = (new PollingCheck(
            $dbgProcessName . ' exited',
            $maxWaitTimeInMicroseconds
        ))->run(
            static function () use ($procOpenRetVal, &$pid, &$exitCode): bool {
                $procStatus = proc_get_status($procOpenRetVal);
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if (!is_array($procStatus)) { // @phpstan-ignore function.alreadyNarrowedType
                    throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('proc_get_status returned value which means an error', compact('procStatus')));
                }

                if ($pid === null) {
                    $pid = AssertEx::isInt($procStatus['pid']);
                }

                if (!AssertEx::isBool($procStatus['running'])) {
                    $exitCode = AssertEx::isInt($procStatus['exitcode']);
                    return true;
                }

                return false;
            }
        );
        $logger->addAllContext(compact('waitFinishedSuccessfully', 'pid', 'exitCode'));
        AssertEx::isNotNull($pid);

        if ($waitFinishedSuccessfully) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Started process exited');
            AssertEx::isNotNull($exitCode);
        } else {
            ($loggerProxyWarning = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxyWarning->log('Wait for the started process to exit timed out');
            AssertEx::isNull($exitCode);
        }

        return compact('pid', 'exitCode');
    }

    public static function terminateProcess(int $pid): bool
    {
        exec("kill $pid > /dev/null", /* ref */ $cmdOutput, /* ref */ $cmdExitCode);
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
     *
     * @return ProcessInfo
     */
    public static function startProcessAndWaitForItToExit(string $dbgProcessName, string $command, array $envVars, int $maxWaitTimeInMicroseconds): array
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('dbgProcessName', 'command', 'envVars'));

        $procOpenRetVal = self::procOpenEx($dbgProcessName, self::addStdErrOutRedirect($dbgProcessName, $command), $envVars, isBackground: false);
        $logger->addAllContext(compact('procOpenRetVal'));

        $procInfo = self::waitForProcessToExitUsingHandle($dbgProcessName, $procOpenRetVal, $maxWaitTimeInMicroseconds);
        if ($procInfo['exitCode'] === null) {
            ($loggerProxyWarning = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxyWarning->log('Wait for the started process to exit timed out - terminating the process now', compact('procInfo'));
            self::terminateProcess(AssertEx::isInt($procInfo['pid']));
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

        return $procInfo;
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
