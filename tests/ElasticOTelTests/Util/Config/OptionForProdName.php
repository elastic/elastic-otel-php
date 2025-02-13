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

use ElasticOTelTests\Util\EnumUtilForTestsTrait;
use PHPUnit\Framework\Assert;

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

    private const OTEL_ENV_VAR_NAME_PREFIX = 'OTEL_';
    private const OTEL_PHP_ENV_VAR_NAME_PREFIX = 'OTEL_PHP_';
    private const ELASTIC_OTEL_ENV_VAR_NAME_PREFIX = 'ELASTIC_OTEL_';

    private const LOG_LEVEL_RELATED = [self::log_level_file, self::log_level_stderr, self::log_level_syslog];
    private const LOG_RELATED = [...self::LOG_LEVEL_RELATED, self::log_file];

    /**
     * @return array<non-empty-string, self[]>
     */
    private static function getEnvVarNamePrefixToOptionNames(): array
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
     * @return list<non-empty-string>
     */
    public static function getEnvVarNamePrefixes(): array
    {
        /** @var ?list<non-empty-string> $envVarNamePrefixes */
        static $envVarNamePrefixes = null;

        if ($envVarNamePrefixes === null) {
            $envVarNamePrefixes = array_keys(self::getEnvVarNamePrefixToOptionNames());
        }

        return $envVarNamePrefixes;
    }

    /**
     * @return non-empty-string
     */
    public function getEnvVarNamePrefix(): string
    {
        /** @var ?array<non-empty-string, non-empty-string> $optNameToEnvVarPrefix */
        static $optNameToEnvVarPrefix = null;

        if ($optNameToEnvVarPrefix === null) {
            $optNameToEnvVarPrefix = [];
            foreach (self::getEnvVarNamePrefixToOptionNames() as $envVarPrefix => $optNames) {
                foreach ($optNames as $currentOptNameCase) {
                    Assert::assertArrayNotHasKey($currentOptNameCase->name, $optNameToEnvVarPrefix);
                    $optNameToEnvVarPrefix[$currentOptNameCase->name] = $envVarPrefix;
                }
            }
        }

        return $optNameToEnvVarPrefix[$this->name];
    }

    public function toEnvVarName(): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName($this->getEnvVarNamePrefix(), $this->name);
    }

    public function isLogLevelRelated(): bool
    {
        return in_array($this, self::LOG_LEVEL_RELATED, strict: true);
    }

    public function isLogRelated(): bool
    {
        return in_array($this, self::LOG_RELATED, strict: true);
    }

    /**
     * @return iterable<self>
     */
    public static function getAllLogRelated(): iterable
    {
        return self::LOG_RELATED;
    }

    /**
     * @return iterable<self>
     */
    public static function getAllLogLevelRelated(): iterable
    {
        return self::LOG_LEVEL_RELATED;
    }
}
