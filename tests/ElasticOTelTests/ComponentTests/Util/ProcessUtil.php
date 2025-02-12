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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 */
final class ProcessUtil
{
    use StaticClassTrait;

    private const PROC_OPEN_DESCRIPTOR_FILE_TYPE = 'file';

    public static function doesProcessExist(int $pid): bool
    {
        exec("ps -p $pid", $cmdOutput, $cmdExitCode);
        return $cmdExitCode === 0;
    }

    public static function waitForProcessToExit(string $dbgProcessDesc, int $pid, int $maxWaitTimeInMicroseconds): bool
    {
        return (new PollingCheck(
            $dbgProcessDesc . ' process (PID: ' . $pid . ') exited' /* <- dbgDesc */,
            $maxWaitTimeInMicroseconds
        ))->run(
            function () use ($pid) {
                return !self::doesProcessExist($pid);
            }
        );
    }

    public static function terminateProcess(int $pid): bool
    {
        exec("kill $pid > /dev/null", /* ref */ $cmdOutput, /* ref */ $cmdExitCode);
        return $cmdExitCode === 0;
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function startBackgroundProcess(string $cmd, array $envVars): void
    {
        self::startProcessImpl("$cmd > /dev/null &", $envVars, descriptorSpec: [], isBackground: true);
    }

    /**
     * @phpstan-param EnvVars $envVars
     */
    public static function startProcessAndWaitUntilExit(string $cmd, array $envVars, bool $shouldCaptureStdOutErr = false, ?int $expectedExitCode = null): int
    {
        $descriptorSpec = [];
        $tempOutputFilePath = '';
        if ($shouldCaptureStdOutErr) {
            $tempOutputFilePath = FileUtil::createTempFile(dbgTempFilePurpose: 'spawn process stdout and stderr');
            $descriptorSpec[1] = [self::PROC_OPEN_DESCRIPTOR_FILE_TYPE, $tempOutputFilePath, "w"]; // 1 - stdout
            $descriptorSpec[2] = [self::PROC_OPEN_DESCRIPTOR_FILE_TYPE, $tempOutputFilePath, "w"]; // 2 - stderr
        }

        $hasReturnedExitCode = false;
        $exitCode = -1;
        try {
            $exitCode = self::startProcessImpl($cmd, $envVars, $descriptorSpec, isBackground: false);
            $hasReturnedExitCode = true;
        } finally {
            $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
            $logLevel = $hasReturnedExitCode ? LogLevel::debug : LogLevel::error;
            $logCtx = [];
            if ($hasReturnedExitCode) {
                $logCtx['exit code'] = $exitCode;
            }
            if ($shouldCaptureStdOutErr) {
                $logCtx['file for stdout + stderr'] = $tempOutputFilePath;
                if (file_exists($tempOutputFilePath)) {
                    $logCtx['stdout + stderr'] = file_get_contents($tempOutputFilePath);
                    Assert::assertTrue(unlink($tempOutputFilePath));
                }
            }

            ($loggerProxy = $logger->ifLevelEnabled($logLevel, __LINE__, __FUNCTION__))
            && $loggerProxy->log($cmd . ' exited', $logCtx);

            if ($expectedExitCode !== null && $hasReturnedExitCode) {
                TestCase::assertSame($expectedExitCode, $exitCode, LoggableToString::convert($logCtx));
            }
        }

        return $exitCode;
    }

    /**
     * @phpstan-param EnvVars                              $envVars
     * @phpstan-param array<array{string, string, string}> $descriptorSpec
     */
    private static function startProcessImpl(string $adaptedCmd, array $envVars, array $descriptorSpec, bool $isBackground): int
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $logger->addAllContext(compact('adaptedCmd', 'envVars', 'isBackground'));

        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Starting process...');

        $pipes = [];
        $openedProc = proc_open($adaptedCmd, $descriptorSpec, /* ref */ $pipes, /* cwd */ null, $envVars);
        if ($openedProc === false) {
            ($loggerProxyError = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxyError->log('Failed to start process');
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Failed to start process', compact('adaptedCmd', 'envVars')));
        }

        $newProcessInfo = proc_get_status($openedProc);
        $pid = ArrayUtil::getValueIfKeyExistsElse('pid', $newProcessInfo, null);
        $logger->addAllContext(compact('pid', 'newProcessInfo'));
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Process started');

        if ($isBackground) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Waiting for process to exit...');
        }
        $exitCode = proc_close($openedProc);
        $logger->addAllContext(compact('exitCode'));
        if ($exitCode === SpawnedProcessBase::FAILURE_PROCESS_EXIT_CODE) {
            ($loggerProxyError = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxyError->log('Process exited with the failure exit code');
            throw new ComponentTestsInfraException(ExceptionUtil::buildMessage('Process exited with the failure exit code', compact('exitCode', 'newProcessInfo', 'adaptedCmd', 'envVars')));
        }

        if ($isBackground) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Process to exited');
        }

        return $exitCode;
    }
}
