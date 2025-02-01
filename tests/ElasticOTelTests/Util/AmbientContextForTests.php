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

namespace ElasticOTelTests\Util;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticOTelTests\Util\Config\CompositeRawSnapshotSource;
use ElasticOTelTests\Util\Config\ConfigSnapshotForTests;
use ElasticOTelTests\Util\Config\EnvVarsRawSnapshotSource;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\Config\OptionsForTestsMetadata;
use ElasticOTelTests\Util\Config\RawSnapshotSourceInterface;
use ElasticOTelTests\Util\Log\Backend as LogBackend;
use ElasticOTelTests\Util\Log\LoggerFactory;
use ElasticOTelTests\Util\Log\SinkForTests;

final class AmbientContextForTests
{
    private static ?self $singletonInstance = null;
    private static ?string $dbgProcessName = null;
    private readonly LogBackend $logBackend;
    private static ?LoggerFactory $loggerFactory = null;
    private readonly Clock $clock;
    private ConfigSnapshotForTests $testConfig;

    private function __construct(string $dbgProcessName)
    {
        self::$dbgProcessName = $dbgProcessName;
        $maxEnabledLogLevelBeforeRealConfig = LogLevel::error;
        $this->logBackend = new LogBackend($maxEnabledLogLevelBeforeRealConfig, new SinkForTests($dbgProcessName));
        self::$loggerFactory = new LoggerFactory($this->logBackend);
        $this->clock = new Clock(self::$loggerFactory);
        // Now that we have a logger we can read real config and see the potential issues with it logged
        $this->readAndApplyConfig();
    }

    public static function init(string $dbgProcessName): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function () use ($dbgProcessName): void {
                if (self::$singletonInstance !== null) {
                    TestCaseBase::assertSame(self::$dbgProcessName, $dbgProcessName);
                    return;
                }

                self::$singletonInstance = new self($dbgProcessName);
            }
        );
    }

    public static function assertIsInited(): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function (): void {
                TestCaseBase::assertNotNull(self::$singletonInstance);
            }
        );
    }

    private static function getSingletonInstance(): self
    {
        return ExceptionUtil::runCatchLogRethrow(
            function (): self {
                TestCaseBase::assertNotNull(self::$singletonInstance);
                return self::$singletonInstance;
            }
        );
    }

    public static function reconfigure(?RawSnapshotSourceInterface $additionalConfigSource = null): void
    {
        self::getSingletonInstance()->readAndApplyConfig($additionalConfigSource);
    }

    private function readAndApplyConfig(?RawSnapshotSourceInterface $additionalConfigSource = null): void
    {
        $envVarConfigSource = new EnvVarsRawSnapshotSource(OptionForTestsName::ENV_VAR_NAME_PREFIX, IterableUtil::keys(OptionsForTestsMetadata::get()));
        $configSource = $additionalConfigSource === null ? $envVarConfigSource : new CompositeRawSnapshotSource([$additionalConfigSource, $envVarConfigSource]);
        $this->testConfig = ConfigUtilForTests::read($configSource, self::loggerFactory());
        $this->logBackend->setMaxEnabledLevel($this->testConfig->logLevel);
    }

    public static function resetLogLevel(LogLevel $newVal): void
    {
        self::resetConfigOption(OptionForTestsName::log_level, $newVal->name);
        TestCaseBase::assertSame($newVal, AmbientContextForTests::testConfig()->logLevel);
    }

    public static function resetEscalatedRerunsMaxCount(int $newVal): void
    {
        self::resetConfigOption(OptionForTestsName::escalated_reruns_max_count, strval($newVal));
        TestCaseBase::assertSame($newVal, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
    }

    private static function resetConfigOption(OptionForTestsName $optName, string $newValAsEnvVar): void
    {
        $envVarName = $optName->toEnvVarName();
        EnvVarUtil::set($envVarName, $newValAsEnvVar);
        AmbientContextForTests::reconfigure();
    }

    public static function testConfig(): ConfigSnapshotForTests
    {
        return self::getSingletonInstance()->testConfig;
    }

    /** @noinspection PhpUnused */
    public static function dbgProcessName(): string
    {
        return ExceptionUtil::runCatchLogRethrow(
            function (): string {
                TestCaseBase::assertNotNull(self::$dbgProcessName);
                return self::$dbgProcessName;
            }
        );
    }

    public static function loggerFactory(): LoggerFactory
    {
        return ExceptionUtil::runCatchLogRethrow(
            function (): LoggerFactory {
                TestCaseBase::assertNotNull(self::$loggerFactory);
                return self::$loggerFactory;
            }
        );
    }

    public static function clock(): Clock
    {
        return self::getSingletonInstance()->clock;
    }
}
