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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel;

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\BoolUtil;
use Elastic\OTel\Util\StaticClassTrait;
use Elastic\OTel\Util\TextUtil;
use OpenTelemetry\SDK\Common\Configuration\Configuration as OTelSdkConfiguration;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTelSdkConfigVariables;
use OpenTelemetry\SDK\Common\Configuration\KnownValues as OTelSdkConfigKnownValues;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RemoteConfigHandler
{
    use StaticClassTrait;

    private const REMOTE_CONFIG_FILE_NAME = 'elastic';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L48
     */
    public const LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME = 'logging_level';
    public const OTEL_LOG_LEVEL_NONE = 'none';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.2.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L106
     */
    public const SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME = 'sampling_rate';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L14
     */
    public const DEACTIVATE_INSTRUMENTATIONS_CONFIG_OPTION_NAME = 'deactivate_instrumentations';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L33
     */
    public const DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME = 'deactivate_all_instrumentations';

    /**
     * @see \OpenTelemetry\SDK\Sdk::OTEL_PHP_DISABLED_INSTRUMENTATIONS_ALL
     * @see \OpenTelemetry\SDK\Sdk::isInstrumentationDisabled
     */
    public const OTEL_PHP_DISABLED_INSTRUMENTATIONS_ALL = 'all';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L104
     */
    public const SEND_LOGS_CONFIG_OPTION_NAME = 'send_logs';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L90
     */
    public const SEND_METRICS_CONFIG_OPTION_NAME = 'send_metrics';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L76
     */
    public const SEND_TRACES_CONFIG_OPTION_NAME = 'send_traces';

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function fetchAndApply(): void
    {
        if (!self::verifyLocalConfigCompatible()) {
            return;
        }

        $fileNameToContent = get_remote_configuration(); // This function is implemented by the extension
        if ($fileNameToContent === null) {
            self::logDebug('extension\'s get_remote_configuration() returned null', __LINE__, __FUNCTION__);
            return;
        }

        if (!is_array($fileNameToContent)) { // @phpstan-ignore function.alreadyNarrowedType
            self::logDebug('extension\'s get_remote_configuration() return value is not an array; value type: ' . get_debug_type($fileNameToContent), __LINE__, __FUNCTION__);
            return;
        }

        self::logDebug('Returned array: ' . self::valueToDbgString($fileNameToContent), __LINE__, __FUNCTION__);

        if (!ArrayUtil::getValueIfKeyExists(self::REMOTE_CONFIG_FILE_NAME, $fileNameToContent, /* out */ $remoteConfigContent)) {
            self::logDebug('Returned array does not contain remote config file name (' . self::REMOTE_CONFIG_FILE_NAME . ')', __LINE__, __FUNCTION__);
            return;
        }

        self::logDebug('Value mapped to remote config file name (' . self::REMOTE_CONFIG_FILE_NAME . ') type: ' . get_debug_type($remoteConfigContent), __LINE__, __FUNCTION__);

        if (!is_string($remoteConfigContent)) {
            self::logError('Value mapped to remote config file name (' . self::REMOTE_CONFIG_FILE_NAME . ') is not a string', __LINE__, __FUNCTION__);
            return;
        }

        self::parseAndApply($remoteConfigContent);
    }

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    private static function verifyLocalConfigCompatible(): bool
    {
        if (OTelSdkConfiguration::has(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE)) {
            $cfgFileOptVal = OTelSdkConfiguration::getMixed(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE);
            if (!is_scalar($cfgFileOptVal)) {
                $cfgFileOptVal = self::valueToDbgString($cfgFileOptVal);
            }
            self::logError(
                'Local config has ' . OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE . ' option set - remote config feature is not compatible with this option'
                . '; ' . OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE . ' option value: ' . $cfgFileOptVal,
                __LINE__,
                __FUNCTION__,
            );
            return false;
        }

        return true;
    }

    private static function logUnexpectedRemoteOptValType(string $remoteOptName, mixed $remoteOptVal, string $dbgTypeDesc): void
    {
        self::logError(
            "Remote config option value type is not as expected; option name: $remoteOptName ; actual value type: " . get_debug_type($remoteOptVal) . "; expected value type: $dbgTypeDesc",
            __LINE__,
            __FUNCTION__,
        );
    }

    /**
     * @param callable(mixed): bool $predicate
     */
    private static function verifyValueType(string $remoteOptName, mixed $remoteOptVal, string $dbgTypeDesc, callable $predicate): bool
    {
        if ($predicate($remoteOptVal)) {
            return true;
        }

        self::logUnexpectedRemoteOptValType($remoteOptName, $remoteOptVal, $dbgTypeDesc);
        return false;
    }

    /**
     * @phpstan-assert-if-true string $remoteOptVal
     */
    private static function verifyValueIsString(string $remoteOptName, mixed $remoteOptVal): bool
    {
        return self::verifyValueType($remoteOptName, $remoteOptVal, 'string', is_string(...));
    }

    /**
     * @param-out float $parsedVal
     *
     * @phpstan-assert-if-true float $parsedVal
     */
    private static function parseValueAsJsonFloat(string $remoteOptName, mixed $remoteOptRawVal, /* out */ ?float &$parsedVal): bool
    {
        if (!self::verifyValueType($remoteOptName, $remoteOptRawVal, 'float', is_numeric(...))) {
            return false;
        }

        /** @var float|int|numeric-string $remoteOptRawVal */
        $parsedVal = floatval($remoteOptRawVal);
        return true;
    }

    /**
     * @param-out bool $parsedVal
     *
     * @phpstan-assert-if-true bool $parsedVal
     */
    private static function parseValueAsJsonBool(string $remoteOptName, mixed $remoteOptRawVal, /* out */ ?bool &$parsedVal): bool
    {
        if (is_string($remoteOptRawVal)) {
            $remoteOptValAsBool = BoolUtil::parseValue($remoteOptRawVal);
            if ($remoteOptValAsBool === null) {
                self::logUnexpectedRemoteOptValType($remoteOptName, $remoteOptRawVal, 'bool as string');
                return false;
            }
            $parsedVal = $remoteOptValAsBool;
            return true;
        }

        if (!self::verifyValueType($remoteOptName, $remoteOptRawVal, 'bool', is_bool(...))) {
            return false;
        }
        /** @var bool $remoteOptRawVal */

        $parsedVal = $remoteOptRawVal;
        return true;
    }

    private static function convertRemoteLoggingLevelToOTel(string $remoteLoggingLevel): ?string
    {
        /**
         * Values used by Remote/Central Configuration:
         * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L59
         *
         * Values used by OTel SDK:
         * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L21
         * @see https://github.com/php-fig/log/blob/1.1.0/Psr/Log/LogLevel.php
         * @see https://github.com/php-fig/log/blob/3.0.2/src/LogLevel.php
         */
        return match ($remoteLoggingLevel) {
            'trace', 'debug' => PsrLogLevel::DEBUG,
            'info' => PsrLogLevel::INFO,
            'warn' => PsrLogLevel::WARNING,
            'error' => PsrLogLevel::ERROR,
            'fatal' => PsrLogLevel::CRITICAL,
            'off' => self::OTEL_LOG_LEVEL_NONE,
            default => null
        };
    }

    private static function logRemoteConfigHasBeenApplied(string $envVarName, ?string $localVal, string $remoteVal, string $mergedVal): void
    {
        self::logDebug('Applied remote config to OTel SDK' . json_encode(compact('envVarName', 'localVal', 'remoteVal', 'mergedVal')), __LINE__, __FUNCTION__);
    }

    /**
     * @param non-empty-string $envVarName
     * @phpstan-param callable(string, string): string $mergeLocalRemote
     */
    private static function setEnvVarToRemoteConfigVal(string $envVarName, string $remoteVal, ?callable $mergeLocalRemote = null): void
    {
        $localVal = self::getOTelConfigWithoutRemote($envVarName);
        PhpPartFacade::setEnvVar($envVarName, $remoteVal);
        $mergedVal = (($mergeLocalRemote === null) || ($localVal === null)) ? $remoteVal : $mergeLocalRemote($localVal, $remoteVal);
        self::logRemoteConfigHasBeenApplied($envVarName, $localVal, $remoteVal, $mergedVal);
    }

    /**
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L59
     */
    private static function parseAndApplyLoggingLevel(mixed $remoteOptVal): void
    {
        if (!self::verifyValueIsString(self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME, $remoteOptVal)) {
            return;
        }
        /** @var string $remoteOptVal */

        $otelLogLevel = self::convertRemoteLoggingLevelToOTel($remoteOptVal);
        if ($otelLogLevel === null) {
            self::logError(
                'Option ' . self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME . " value is not in the set of the expected values: $remoteOptVal",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        /**
         * OTel SDK reads log level config directly from $_SERVER
         * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L72
         */
        $envVarName = OTelSdkConfigVariables::OTEL_LOG_LEVEL;
        $localOptVal = $_SERVER[$envVarName];
        if (!is_string($localOptVal)) {
            $localOptVal = self::valueToDbgString($localOptVal);
        }
        $_SERVER[$envVarName] = $otelLogLevel;
        self::logRemoteConfigHasBeenApplied($envVarName, $localOptVal, $otelLogLevel, mergedVal: $otelLogLevel);
    }

    /**
     * @template T of int|float
     *
     * @phpstan-param T $rangeBegin
     * @phpstan-param T $actual
     * @phpstan-param T $rangeInclusiveEnd
     */
    private static function isInClosedRange(int|float $rangeBegin, int|float $actual, int|float $rangeInclusiveEnd): bool
    {
        return ($rangeBegin <= $actual) && ($actual <= $rangeInclusiveEnd);
    }

    private static function getOTelConfigWithoutRemote(string $otelOptEnvVarName): ?string
    {
        $envVarVal = getenv($otelOptEnvVarName);
        return is_string($envVarVal) ? $envVarVal : null;
    }

    /**
     * @see https://github.com/elastic/kibana/blob/v9.2.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L107
     */
    private static function parseAndApplySamplingRate(mixed $remoteOptVal): void
    {
        if (!self::parseValueAsJsonFloat(self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME, $remoteOptVal, /* out */ $remoteOptValAsFloat)) {
            return;
        }

        if (!self::isInClosedRange(0, $remoteOptValAsFloat, 1)) {
            self::logError(
                'Option ' . self::SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME . " value is not between 0 and 1: $remoteOptValAsFloat",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        $otelConfigSampler = self::getOTelConfigWithoutRemote(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER);
        if ($otelConfigSampler === null) {
            self::setEnvVarToRemoteConfigVal(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER, OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO);
        } elseif ($otelConfigSampler !== OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO) {
            self::logDebug(
                'OpenTelemetry SDK configuration option ' . OTelSdkConfigVariables::OTEL_TRACES_SAMPLER
                . " is set to value not compatible with EDOT's remote configuration feature (value: $otelConfigSampler)"
                . " - not applying sampling rate received via remote configuration (value: $remoteOptValAsFloat).",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        self::setEnvVarToRemoteConfigVal(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG, strval($remoteOptValAsFloat));
    }

    /**
     * @param non-empty-string $otelEnvVarName
     */
    private static function parseAndApplySendSignal(string $remoteOptName, mixed $remoteOptVal, string $otelEnvVarName): void
    {
        if (!self::parseValueAsJsonBool($remoteOptName, $remoteOptVal, /* out */ $shouldSend)) {
            return;
        }

        if (!$shouldSend) {
            self::setEnvVarToRemoteConfigVal($otelEnvVarName, OTelSdkConfigKnownValues::VALUE_NONE);
        }
    }

    private static function parseAndApplyOption(string $remoteOptName, mixed $remoteOptVal): void
    {
        self::logDebug(
            'Entered'
            . '; option name: ' . $remoteOptName
            . '; value type: ' . get_debug_type($remoteOptVal)
            . '; value: ' . self::valueToDbgString($remoteOptVal),
            __LINE__,
            __FUNCTION__,
        );

        match ($remoteOptName) {
            // deactivate_all_instrumentations and deactivate_instrumentations are handled in the caller
            self::DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME, self::DEACTIVATE_INSTRUMENTATIONS_CONFIG_OPTION_NAME => null,
            self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME => self::parseAndApplyLoggingLevel($remoteOptVal),
            self::SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME => self::parseAndApplySamplingRate($remoteOptVal),
            self::SEND_LOGS_CONFIG_OPTION_NAME => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_LOGS_EXPORTER),
            self::SEND_METRICS_CONFIG_OPTION_NAME => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_METRICS_EXPORTER),
            self::SEND_TRACES_CONFIG_OPTION_NAME => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_TRACES_EXPORTER),
            default => self::logDebug(
                'Encountered an option that is not supported as remote configuration option'
                . '; option name: ' . $remoteOptName
                . '; value type: ' . get_debug_type($remoteOptVal)
                . '; value: ' . self::valueToDbgString($remoteOptVal),
                __LINE__,
                __FUNCTION__,
            )
        };
    }

    /**
     * @return array<string>
     */
    private static function parseCommaSeparatedList(string $commaSeparatedList): array
    {
        if (TextUtil::isEmptyString(trim($commaSeparatedList))) {
            return [];
        }

        return array_map(trim(...), explode(',', $commaSeparatedList));
    }

    public static function mergeDisabledInstrumentations(string $localVal, string $remoteVal): string
    {
        return implode(',', array_merge(self::parseCommaSeparatedList($localVal), self::parseCommaSeparatedList($remoteVal)));
    }

    /**
     * @param array<array-key, mixed> $remoteOptNameToVal
     *
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L15
     * @see \OpenTelemetry\SDK\Sdk::isInstrumentationDisabled
     */
    private static function parseAndApplyDeactivateInstrumentations(array $remoteOptNameToVal): void
    {
        $deactivateAllInstrumentations = false;
        if (
            array_key_exists(self::DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME, $remoteOptNameToVal)
            && self::parseValueAsJsonBool(
                self::DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME,
                $remoteOptNameToVal[self::DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME],
                $parsedDeactivateAllInstrumentations /* <- out */,
            )
        ) {
            $deactivateAllInstrumentations = $parsedDeactivateAllInstrumentations;
        }

        $deactivateInstrumentationsVal = null;
        if (
            array_key_exists(self::DEACTIVATE_INSTRUMENTATIONS_CONFIG_OPTION_NAME, $remoteOptNameToVal)
            && self::verifyValueIsString(
                self::DEACTIVATE_INSTRUMENTATIONS_CONFIG_OPTION_NAME,
                ($deactivateInstrumentationsRemoteVal = $remoteOptNameToVal[self::DEACTIVATE_ALL_INSTRUMENTATIONS_CONFIG_OPTION_NAME]),
            )
        ) {
            $deactivateInstrumentationsVal = $deactivateInstrumentationsRemoteVal;
        }

        if ($deactivateAllInstrumentations) {
            self::setEnvVarToRemoteConfigVal(OTelSdkConfigVariables::OTEL_PHP_DISABLED_INSTRUMENTATIONS, self::OTEL_PHP_DISABLED_INSTRUMENTATIONS_ALL);
        } elseif ($deactivateInstrumentationsVal != null) {
            self::setEnvVarToRemoteConfigVal(OTelSdkConfigVariables::OTEL_PHP_DISABLED_INSTRUMENTATIONS, $deactivateInstrumentationsVal, self::mergeDisabledInstrumentations(...));
        }
    }

    private static function parseAndApply(string $remoteConfigContent): void
    {
        if (($remoteOptNameToVal = self::decodeRemoteConfig($remoteConfigContent)) === null) {
            return;
        }

        foreach ($remoteOptNameToVal as $remoteOptName => $remoteOptVal) {
            self::parseAndApplyOption($remoteOptName, $remoteOptVal);
        }

        self::parseAndApplyDeactivateInstrumentations($remoteOptNameToVal);
    }

    private static function logDebug(string $message, int $lineNumber, string $func): void
    {
        self::logWithLevel(BootstrapStageLogger::LEVEL_DEBUG, $message, $lineNumber, $func);
    }

    private static function logError(string $message, int $lineNumber, string $func): void
    {
        self::logWithLevel(BootstrapStageLogger::LEVEL_ERROR, $message, $lineNumber, $func);
    }

    private static function logWithLevel(int $statementLevel, string $message, int $lineNumber, string $func): void
    {
        BootstrapStageLogger::logWithFeatureAndLevel(Log\LogFeature::OPAMP, $statementLevel, $message, __FILE__, $lineNumber, __CLASS__, $func);
    }

    public static function valueToDbgString(mixed $value): string
    {
        $options = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES;
        $encodedData = json_encode($value, $options);
        if ($encodedData === false) {
            return 'json_encode() failed'
                   . '. json_last_error_msg(): ' . json_last_error_msg()
                   . '. data type: ' . get_debug_type($value);
        }
        return $encodedData;
    }

    /**
     * @return ?array<array-key, mixed>
     */
    private static function decodeRemoteConfig(string $remoteConfigContent): ?array
    {
        $decodedData = json_decode($remoteConfigContent, /* assoc: */ true);
        if ($decodedData === null) {
            self::logError('json_decode() failed. json_last_error_msg(): ' . json_last_error_msg() . '.' . ' remoteConfigContent: `' . $remoteConfigContent . '\'', __LINE__, __FUNCTION__);
            return null;
        }

        if (!is_array($decodedData)) {
            self::logError('JSON decoded value mapped to remote config file name is not an array; value type: ' . get_debug_type($decodedData), __LINE__, __FUNCTION__);
            return null;
        }

        self::logDebug('JSON decoded value mapped to remote config file name: ' . json_encode($decodedData), __LINE__, __FUNCTION__);

        return $decodedData;
    }
}
