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

use Elastic\OTel\Config\OTelConfigOptionValues;
use Elastic\OTel\Config\RemoteConfigOptionName;
use Elastic\OTel\Log\RemoteConfigLoggingLevel;
use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\BoolUtil;
use Elastic\OTel\Util\StaticClassTrait;
use OpenTelemetry\SDK\Common\Configuration\Configuration as OTelSdkConfiguration;
use OpenTelemetry\SDK\Common\Configuration\KnownValues as OTelSdkConfigKnownValues;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTelSdkConfigVariables;

/**
 * @phpstan-type ElasticFileDecodedBody array<array-key, mixed>
 */
final class RemoteConfigHandler
{
    use StaticClassTrait;

    public const ELASTIC_FILE_NAME = 'elastic';

    public static function fetchAndApply(): void
    {
        if (!self::verifyLocalConfigCompatible()) {
            return;
        }

        $elasticCfgFileEncodedBody = get_remote_configuration(self::ELASTIC_FILE_NAME); // This function is implemented by the extension
        if ($elasticCfgFileEncodedBody === null) {
            self::logDebug(
                'extension\'s get_remote_configuration("' . self::ELASTIC_FILE_NAME . '") returned null'
                . ' ; get_remote_configuration() return value: ' . self::valueToDbgString(get_remote_configuration()),
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        if (!is_string($elasticCfgFileEncodedBody)) {
            self::logError(
                'Value mapped to "' . self::ELASTIC_FILE_NAME . '" remote config file name is not a string'
                . ' ; the actual type: ' . get_debug_type($elasticCfgFileEncodedBody),
                __LINE__,
                __FUNCTION__,
            );
            return;
        }

        if (($remoteCfgOptKeyToValMap = self::decodeElasticRemoteConfigFileBody($elasticCfgFileEncodedBody)) === null) {
            return;
        }

        self::parseAndApplyOptionNameToValueMap($remoteCfgOptKeyToValMap);
    }

    private static function verifyLocalConfigCompatible(): bool
    {
        if (OTelSdkConfiguration::has(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE)) {
            $dbgCfgFileOptVal = OTelSdkConfiguration::getMixed(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE);
            if (!is_scalar($dbgCfgFileOptVal)) {
                $dbgCfgFileOptVal = self::valueToDbgString($dbgCfgFileOptVal);
            }
            self::logWarning(
                'Local config has ' . OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE . ' option set - remote config feature is not compatible with this option'
                . '; ' . OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE . ' option value: ' . $dbgCfgFileOptVal,
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

    private static function logRemoteConfigHasBeenApplied(string $remoteOptName, string $remoteOptVal, string $otelEnvVarName, string $otelEnvVarValFromRemote): void
    {
        self::logDebug('Applied remote config to OTel SDK' . json_encode(compact('remoteOptName', 'remoteOptVal', 'otelEnvVarName', 'otelEnvVarValFromRemote')), __LINE__, __FUNCTION__);
    }

    /**
     * @param non-empty-string $otelEnvVarName
     */
    private static function setEnvVarToRemoteConfigVal(string $remoteOptName, string $remoteOptVal, string $otelEnvVarName, string $otelEnvVarValFromRemote): void
    {
        PhpPartFacade::setEnvVar($otelEnvVarName, $otelEnvVarValFromRemote);
        self::logRemoteConfigHasBeenApplied($remoteOptName, $remoteOptVal, $otelEnvVarName, $otelEnvVarValFromRemote);
    }

    /**
     * @param ElasticFileDecodedBody $remoteCfgOptKeyToValMap
     *
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L15
     * @see \OpenTelemetry\SDK\Sdk::isInstrumentationDisabled
     */
    private static function parseAndApplyDeactivateInstrumentations(array $remoteCfgOptKeyToValMap): void
    {
        $deactivateAllInstrumentations = false;
        if (
            ArrayUtil::getValueIfKeyExists(RemoteConfigOptionName::deactivate_all_instrumentations->name, $remoteCfgOptKeyToValMap, /* out */ $rawDeactivateAllInstrumentations)
            && self::parseValueAsJsonBool(RemoteConfigOptionName::deactivate_all_instrumentations->name, $rawDeactivateAllInstrumentations, /* out */ $parsedDeactivateAllInstrumentations)
        ) {
            $deactivateAllInstrumentations = $parsedDeactivateAllInstrumentations;
        }

        $deactivateInstrumentations = null;
        if (
            ArrayUtil::getValueIfKeyExists(RemoteConfigOptionName::deactivate_instrumentations->name, $remoteCfgOptKeyToValMap, /* out */ $rawDeactivateInstrumentations)
            && self::verifyValueIsString(RemoteConfigOptionName::deactivate_instrumentations->name, $rawDeactivateInstrumentations)
        ) {
            $deactivateInstrumentations = $rawDeactivateInstrumentations;
        }

        if ($deactivateAllInstrumentations) {
            self::setEnvVarToRemoteConfigVal(
                RemoteConfigOptionName::deactivate_all_instrumentations->name,
                $rawDeactivateAllInstrumentations,
                OTelSdkConfigVariables::OTEL_PHP_DISABLED_INSTRUMENTATIONS,
                OTelConfigOptionValues::DISABLED_INSTRUMENTATIONS_ALL,
            );
        } elseif ($deactivateInstrumentations != null) {
            self::setEnvVarToRemoteConfigVal(
                RemoteConfigOptionName::deactivate_instrumentations->name,
                $deactivateInstrumentations,
                OTelSdkConfigVariables::OTEL_PHP_DISABLED_INSTRUMENTATIONS,
                $deactivateInstrumentations,
            );
        }
    }

    /**
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L59
     */
    private static function parseAndApplyLoggingLevel(string $remoteOptName, mixed $remoteOptVal): void
    {
        if (!self::verifyValueIsString($remoteOptName, $remoteOptVal)) {
            return;
        }
        /** @var string $remoteOptVal */

        $remoteConfigLoggingLevel = RemoteConfigLoggingLevel::tryToFindByName($remoteOptVal);
        if ($remoteConfigLoggingLevel === null) {
            self::logError("Option $remoteOptName value is not in the set of the expected values: $remoteOptVal", __LINE__, __FUNCTION__);
            return;
        }
        $otelLogLevel = $remoteConfigLoggingLevel->toOTelInternalLogLevel()->name;

        /**
         * OTel SDK reads log level config directly from $_SERVER
         * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L72
         */
        $otelEnvVarName = OTelSdkConfigVariables::OTEL_LOG_LEVEL;
        $_SERVER[$otelEnvVarName] = $otelLogLevel;
        self::logRemoteConfigHasBeenApplied($remoteOptName, $remoteOptVal, $otelEnvVarName, $otelLogLevel);
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
    private static function parseAndApplySamplingRate(string $remoteOptName, mixed $remoteOptVal): void
    {
        if (!self::parseValueAsJsonFloat($remoteOptName, $remoteOptVal, /* out */ $remoteOptValAsFloat)) {
            return;
        }
        /** @var float|int|numeric-string $remoteOptVal */
        $remoteOptValAsFloat = floatval($remoteOptVal);

        if (!self::isInClosedRange(0, $remoteOptValAsFloat, 1)) {
            self::logError("Option $remoteOptName value is not between 0 and 1: $remoteOptValAsFloat", __LINE__, __FUNCTION__);
            return;
        }

        $otelConfigSampler = self::getOTelConfigWithoutRemote(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER);
        if ($otelConfigSampler !== null && $otelConfigSampler !== OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO) {
            self::logDebug(
                'OpenTelemetry SDK configuration option ' . OTelSdkConfigVariables::OTEL_TRACES_SAMPLER
                . " is set to value not compatible with EDOT's remote configuration feature (value: $otelConfigSampler)"
                . " - not applying sampling rate received via remote configuration (value: $remoteOptValAsFloat).",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        self::setEnvVarToRemoteConfigVal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG, strval($remoteOptValAsFloat));
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
            self::setEnvVarToRemoteConfigVal($remoteOptName, $remoteOptVal, $otelEnvVarName, OTelSdkConfigKnownValues::VALUE_NONE);
        }
    }

    private static function dispatchParseAndApplyOption(string $remoteOptName, mixed $remoteOptVal): void
    {
        self::logDebug(
            'Entered'
            . '; option name: ' . $remoteOptName
            . '; value type: ' . get_debug_type($remoteOptVal)
            . '; value: ' . self::valueToDbgString($remoteOptVal),
            __LINE__,
            __FUNCTION__,
        );

        if (($remoteOptNameEnum = RemoteConfigOptionName::tryToFindByName($remoteOptName)) === null) {
            self::logDebug(
                'Encountered an option that is not supported as remote configuration option'
                . '; option name: ' . $remoteOptName
                . '; value type: ' . get_debug_type($remoteOptVal)
                . '; value: ' . self::valueToDbgString($remoteOptVal),
                __LINE__,
                __FUNCTION__,
            );
            return;
        }

        match ($remoteOptNameEnum) {
            // deactivate_all_instrumentations and deactivate_instrumentations are handled in the caller
            RemoteConfigOptionName::deactivate_all_instrumentations, RemoteConfigOptionName::deactivate_instrumentations => null,
            RemoteConfigOptionName::logging_level => self::parseAndApplyLoggingLevel($remoteOptName, $remoteOptVal),
            RemoteConfigOptionName::sampling_rate => self::parseAndApplySamplingRate($remoteOptName, $remoteOptVal),
            RemoteConfigOptionName::send_logs => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_LOGS_EXPORTER),
            RemoteConfigOptionName::send_metrics => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_METRICS_EXPORTER),
            RemoteConfigOptionName::send_traces => self::parseAndApplySendSignal($remoteOptName, $remoteOptVal, OTelSdkConfigVariables::OTEL_TRACES_EXPORTER),
        };
    }

    /**
     * @param ElasticFileDecodedBody $remoteCfgOptKeyToValMap
     */
    private static function parseAndApplyOptionNameToValueMap(array $remoteCfgOptKeyToValMap): void
    {
        foreach ($remoteCfgOptKeyToValMap as $remoteOptName => $remoteOptVal) {
            self::dispatchParseAndApplyOption($remoteOptName, $remoteOptVal);
        }

        // deactivate_all_instrumentations and deactivate_instrumentations are skipped in dispatchParseAndApplyOption
        // because if deactivate_all_instrumentations is true it overrides deactivate_instrumentations
        self::parseAndApplyDeactivateInstrumentations($remoteCfgOptKeyToValMap);
    }

    private static function logDebug(string $message, int $lineNumber, string $func): void
    {
        self::logWithLevel(BootstrapStageLogger::LEVEL_DEBUG, $message, $lineNumber, $func);
    }

    private static function logWarning(string $message, int $lineNumber, string $func): void
    {
        self::logWithLevel(BootstrapStageLogger::LEVEL_WARNING, $message, $lineNumber, $func);
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
        if (is_string($value)) {
            return $value;
        }

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
     * @return ?ElasticFileDecodedBody
     */
    private static function decodeElasticRemoteConfigFileBody(string $remoteConfigContent): ?array
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
