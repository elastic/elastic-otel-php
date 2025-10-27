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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\WildcardListMatcher;
use ElasticOTelTests\Util\Duration;
use ElasticOTelTests\Util\Log\LoggableInterface;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ConfigSnapshotForProd implements LoggableInterface
{
    use SnapshotTrait;

    private readonly ?bool $autoloadEnabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?string $bootstrapPhpPartFile; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?WildcardListMatcher $disabledInstrumentations; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $enabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?string $exporterOtlpEndpoint; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $inferredSpansEnabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly Duration $inferredSpansMinDuration; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $inferredSpansReductionEnabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly Duration $inferredSpansSamplingInterval; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $inferredSpansStacktraceEnabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?string $logFile; // @phpstan-ignore property.uninitializedReadonly
    private readonly LogLevel $logLevelFile; // @phpstan-ignore property.uninitializedReadonly
    private readonly LogLevel $logLevelStderr; // @phpstan-ignore property.uninitializedReadonly
    private readonly LogLevel $logLevelSyslog; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?string $resourceAttributes; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $transactionSpanEnabled; // @phpstan-ignore property.uninitializedReadonly
    private readonly bool $transactionSpanEnabledCli; // @phpstan-ignore property.uninitializedReadonly

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        self::setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function effectiveLogLevel(): LogLevel
    {
        $maxFoundLevel = LogLevel::off;

        $keepMaxLevel = function (LogLevel $logLevel) use (&$maxFoundLevel): void {
            if ($logLevel->value > $maxFoundLevel->value) {
                $maxFoundLevel = $logLevel;
            }
        };

        $keepMaxLevel($this->logLevelStderr);
        $keepMaxLevel($this->logLevelSyslog);
        if ($this->logFile !== null) {
            $keepMaxLevel($this->logLevelFile);
        }

        return $maxFoundLevel;
    }

    /** @noinspection PhpUnused */
    public function isInstrumentationDisabled(string $name): bool
    {
        if ($this->disabledInstrumentations === null) {
            return false;
        }

        /**
         * @see \OpenTelemetry\SDK\Sdk::isInstrumentationDisabled
         * @see \OpenTelemetry\SDK\Sdk::OTEL_PHP_DISABLED_INSTRUMENTATIONS_ALL
         */
        return $this->disabledInstrumentations->match('all') || $this->disabledInstrumentations->match($name);
    }
}
