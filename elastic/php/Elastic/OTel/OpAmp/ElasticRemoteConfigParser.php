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

namespace Elastic\OTel\OpAmp;

/**
 * Parses and applies Elastic-specific remote configuration received via OpAMP.
 *
 * This class is intentionally self-contained and does NOT reference upstream scoped classes
 * (OTelDistroScoped\*) because it lives outside the scoper bubble. OTel env var names and
 * known values are inlined as string constants.
 *
 * Option names are aligned with Kibana's EDOT SDK settings:
 * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts
 *
 * @internal
 */
final class ElasticRemoteConfigParser
{
    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L48 */
    private const LOGGING_LEVEL = 'logging_level';

    /** @see https://github.com/elastic/kibana/blob/v9.2.0/.../edot_sdk_settings.ts#L106 */
    private const SAMPLING_RATE = 'sampling_rate';

    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L14 */
    private const DEACTIVATE_INSTRUMENTATIONS = 'deactivate_instrumentations';

    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L33 */
    private const DEACTIVATE_ALL_INSTRUMENTATIONS = 'deactivate_all_instrumentations';

    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L76 */
    private const SEND_TRACES = 'send_traces';

    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L90 */
    private const SEND_METRICS = 'send_metrics';

    /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L104 */
    private const SEND_LOGS = 'send_logs';

    // OTel env var names (inlined to avoid scoped class dependency)
    // @see OpenTelemetry\SDK\Common\Configuration\Variables
    private const OTEL_LOG_LEVEL = 'OTEL_LOG_LEVEL';
    private const OTEL_TRACES_EXPORTER = 'OTEL_TRACES_EXPORTER';
    private const OTEL_METRICS_EXPORTER = 'OTEL_METRICS_EXPORTER';
    private const OTEL_LOGS_EXPORTER = 'OTEL_LOGS_EXPORTER';
    private const OTEL_TRACES_SAMPLER = 'OTEL_TRACES_SAMPLER';
    private const OTEL_TRACES_SAMPLER_ARG = 'OTEL_TRACES_SAMPLER_ARG';
    private const OTEL_PHP_DISABLED_INSTRUMENTATIONS = 'OTEL_PHP_DISABLED_INSTRUMENTATIONS';

    // OTel known values (inlined)
    // @see OpenTelemetry\SDK\Common\Configuration\KnownValues
    private const VALUE_NONE = 'none';
    private const VALUE_PARENT_BASED_TRACE_ID_RATIO = 'parentbased_traceidratio';

    private const DISABLED_INSTRUMENTATIONS_ALL = 'all';

    /**
     * @param array<string, mixed> $config
     */
    public static function parseAndApply(array $config): void
    {
        self::logDebug('Parsing remote config', $config);

        foreach ($config as $optName => $optVal) {
            match ($optName) {
                // Handled separately below
                self::DEACTIVATE_ALL_INSTRUMENTATIONS, self::DEACTIVATE_INSTRUMENTATIONS => null,
                self::LOGGING_LEVEL => self::applyLoggingLevel($optVal),
                self::SAMPLING_RATE => self::applySamplingRate($optVal),
                self::SEND_TRACES => self::applySendSignal($optName, $optVal, self::OTEL_TRACES_EXPORTER),
                self::SEND_METRICS => self::applySendSignal($optName, $optVal, self::OTEL_METRICS_EXPORTER),
                self::SEND_LOGS => self::applySendSignal($optName, $optVal, self::OTEL_LOGS_EXPORTER),
                default => self::logDebug('Unsupported remote config option', ['option' => $optName]),
            };
        }

        self::applyDeactivateInstrumentations($config);
    }

    private static function applyLoggingLevel(mixed $val): void
    {
        if (!is_string($val)) {
            self::logError('logging_level value is not a string: ' . get_debug_type($val));
            return;
        }

        /** @see https://github.com/elastic/kibana/blob/v9.1.0/.../edot_sdk_settings.ts#L59 */
        $otelLevel = match ($val) {
            'trace', 'debug' => 'debug',
            'info' => 'info',
            'warn' => 'warning',
            'error' => 'error',
            'fatal' => 'critical',
            'off' => 'none',
            default => null,
        };

        if ($otelLevel === null) {
            self::logError("Unknown logging_level value: $val");
            return;
        }

        // OTel SDK reads log level directly from $_SERVER
        $_SERVER[self::OTEL_LOG_LEVEL] = $otelLevel;
        self::logDebug('Applied logging_level', ['remote' => $val, 'otel' => $otelLevel]);
    }

    private static function applySamplingRate(mixed $val): void
    {
        if (!is_numeric($val)) {
            self::logError('sampling_rate value is not numeric: ' . get_debug_type($val));
            return;
        }

        $rate = floatval($val);
        if ($rate < 0 || $rate > 1) {
            self::logError("sampling_rate value out of range [0,1]: $rate");
            return;
        }

        $currentSampler = self::getEnvVar(self::OTEL_TRACES_SAMPLER);
        if ($currentSampler === null || $currentSampler === '') {
            self::setEnvVar(self::OTEL_TRACES_SAMPLER, self::VALUE_PARENT_BASED_TRACE_ID_RATIO);
        } elseif ($currentSampler !== self::VALUE_PARENT_BASED_TRACE_ID_RATIO) {
            self::logDebug(
                'OTEL_TRACES_SAMPLER is set to incompatible value, not applying sampling_rate',
                ['sampler' => $currentSampler, 'rate' => $rate],
            );
            return;
        }

        self::setEnvVar(self::OTEL_TRACES_SAMPLER_ARG, strval($rate));
        self::logDebug('Applied sampling_rate', ['rate' => $rate]);
    }

    private static function applySendSignal(string $optName, mixed $val, string $otelEnvVar): void
    {
        $shouldSend = self::parseBool($optName, $val);
        if ($shouldSend === null) {
            return;
        }

        if (!$shouldSend) {
            self::setEnvVar($otelEnvVar, self::VALUE_NONE);
            self::logDebug("Applied $optName=false", ['envVar' => $otelEnvVar]);
        } else {
            // Remove any prior override so the default exporter resumes.
            // putenv() values persist across PHP-FPM requests in the same worker.
            self::unsetEnvVar($otelEnvVar);
            self::logDebug("Applied $optName=true (unset override)", ['envVar' => $otelEnvVar]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function applyDeactivateInstrumentations(array $config): void
    {
        $deactivateAll = false;
        if (array_key_exists(self::DEACTIVATE_ALL_INSTRUMENTATIONS, $config)) {
            $parsed = self::parseBool(self::DEACTIVATE_ALL_INSTRUMENTATIONS, $config[self::DEACTIVATE_ALL_INSTRUMENTATIONS]);
            if ($parsed === true) {
                $deactivateAll = true;
            }
        }

        if ($deactivateAll) {
            self::setEnvVar(self::OTEL_PHP_DISABLED_INSTRUMENTATIONS, self::DISABLED_INSTRUMENTATIONS_ALL);
            self::logDebug('Applied deactivate_all_instrumentations=true');
            return;
        }

        if (array_key_exists(self::DEACTIVATE_INSTRUMENTATIONS, $config)) {
            $val = $config[self::DEACTIVATE_INSTRUMENTATIONS];
            if (!is_string($val)) {
                self::logError('deactivate_instrumentations value is not a string: ' . get_debug_type($val));
                return;
            }

            // Set remote value directly — do NOT merge with getenv() because in PHP-FPM
            // workers putenv() values persist across requests, causing stale entries
            // from previous remote configs to accumulate.
            self::setEnvVar(self::OTEL_PHP_DISABLED_INSTRUMENTATIONS, $val);
            self::logDebug('Applied deactivate_instrumentations', ['value' => $val]);
        }
    }

    private static function parseBool(string $optName, mixed $val): ?bool
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val)) {
            return $val !== 0;
        }
        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === 'true' || $lower === '1' || $lower === 'yes' || $lower === 'on') {
                return true;
            }
            if ($lower === 'false' || $lower === '0' || $lower === 'no' || $lower === 'off') {
                return false;
            }
        }
        self::logError("Cannot parse $optName as bool: " . get_debug_type($val));
        return null;
    }

    /**
     * @return array<string>
     */
    private static function parseCommaSeparatedList(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }
        return array_map(trim(...), explode(',', $csv));
    }

    private static function setEnvVar(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $_SERVER[$name] = $value;
    }

    private static function unsetEnvVar(string $name): void
    {
        putenv($name);
        unset($_SERVER[$name]);
    }

    /**
     * Read env var checking $_SERVER first, then getenv() — matching OTel SDK resolution order.
     */
    private static function getEnvVar(string $name): ?string
    {
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
            return $_SERVER[$name];
        }
        $val = getenv($name);
        return $val !== false ? $val : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function logDebug(string $message, array $context = []): void
    {
        $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : '';
        error_log('[EDOT] [DEBUG] [ElasticRemoteConfigParser] ' . $message . $contextStr);
    }

    private static function logError(string $message): void
    {
        error_log('[EDOT] [ERROR] [ElasticRemoteConfigParser] ' . $message);
    }
}