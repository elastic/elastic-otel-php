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

namespace ElasticOTelTests\Util\Config;

use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\EnumUtilForTestsTrait;
use ElasticOTelTests\Util\TestCaseBase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
enum OptionForProdName
{
    use EnumUtilForTestsTrait;

    case autoload_enabled;
    case bootstrap_php_part_file;
    case disabled_instrumentations;
    case enabled;
    case exporter_otlp_endpoint;
    case log_file;
    case log_level_file;
    case log_level_stderr;
    case log_level_syslog;
    case transaction_span_enabled;
    case transaction_span_enabled_cli;

    public const OTEL_ENV_VAR_NAME_PREFIX = 'OTEL_';
    public const OTEL_PHP_ENV_VAR_NAME_PREFIX = 'OTEL_PHP_';
    public const ELASTIC_OTEL_ENV_VAR_NAME_PREFIX = 'ELASTIC_OTEL_';

    public const LOG_LEVEL_RELATED = [self::log_level_file, self::log_level_stderr, self::log_level_syslog];

    /**
     * @return array<string, self[]>
     */
    public static function getEnvVarNamePrefixToOptionNames(): array
    {
        $otelPrefix = [
            self::exporter_otlp_endpoint,
        ];

        $otelPhpPrefix = [
            self::autoload_enabled,
            self::disabled_instrumentations,
        ];

        $elasticOTelPrefix = [
            self::bootstrap_php_part_file,
            self::enabled,
            self::log_file,
            self::log_level_file,
            self::log_level_stderr,
            self::log_level_syslog,
            self::transaction_span_enabled,
            self::transaction_span_enabled_cli,
        ];

        return [
            self::OTEL_ENV_VAR_NAME_PREFIX => $otelPrefix,
            self::OTEL_PHP_ENV_VAR_NAME_PREFIX => $otelPhpPrefix,
            self::ELASTIC_OTEL_ENV_VAR_NAME_PREFIX => $elasticOTelPrefix,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getEnvVarNamePrefixes(): array
    {
        /** @var ?list<string> $envVarNamePrefixes */
        static $envVarNamePrefixes = null;

        if ($envVarNamePrefixes === null) {
            $envVarNamePrefixes = array_keys(self::getEnvVarNamePrefixToOptionNames());
        }

        return $envVarNamePrefixes;
    }

    /**
     * @return array<string, string>
     */
    private static function buildOptionNameToEnvVarName(): array
    {
        /** @var array<string, string> $optNameToEnvVarName */
        $optNameToEnvVarName = [];
        foreach (self::getEnvVarNamePrefixToOptionNames() as $envVarPrefix => $optNames) {
            foreach ($optNames as $currentOptNameCase) {
                TestCaseBase::assertArrayNotHasKey($currentOptNameCase->name, $optNameToEnvVarName);
                $optNameToEnvVarName[$currentOptNameCase->name] = EnvVarsRawSnapshotSource::optionNameToEnvVarName($envVarPrefix, $currentOptNameCase->name);
            }
        }

        self::assertCorrectOptionNameToEnvVarName($optNameToEnvVarName);
        return $optNameToEnvVarName;
    }

    /**
     * @param array<string, string> $optNameToEnvVarName
     */
    private static function assertCorrectOptionNameToEnvVarName(array $optNameToEnvVarName): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        TestCaseBase::assertCount(count(self::cases()), $optNameToEnvVarName);
        $envVarPrefixes = self::getEnvVarNamePrefixes();
        $dbgCtx->pushSubScope();
        foreach (self::cases() as $currentOptNameCase) {
            $dbgCtx->add(compact('currentOptNameCase'));
            TestCaseBase::assertArrayHasKey($currentOptNameCase->name, $optNameToEnvVarName);
            $currentEnvVarName = $optNameToEnvVarName[$currentOptNameCase->name];
            $dbgCtx->add(compact('currentEnvVarName'));
            $foundPrefix = false;
            foreach ($envVarPrefixes as $envVarPrefix) {
                $envVarNameCandidate = EnvVarsRawSnapshotSource::optionNameToEnvVarName($envVarPrefix, $currentOptNameCase->name);
                if ($envVarNameCandidate === $currentEnvVarName) {
                    $foundPrefix = true;
                    break;
                }
            }
            TestCaseBase::assertTrue($foundPrefix);
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pop();
    }

    public static function toEnvVarName(self $optName): string
    {
        /** @var ?array<string, string> $optNameToEnvVarName */
        static $optNameToEnvVarName = null;

        if ($optNameToEnvVarName === null) {
            $optNameToEnvVarName = self::buildOptionNameToEnvVarName();
        }

        return $optNameToEnvVarName[$optName->name];
    }

    public function isLogLevelRelated(): bool
    {
        return in_array($this, self::LOG_LEVEL_RELATED, strict: true);
    }

    /**
     * @return iterable<OptionForProdName>
     */
    public static function getAllLogLevelRelated(): iterable
    {
        return self::LOG_LEVEL_RELATED;
    }
}
