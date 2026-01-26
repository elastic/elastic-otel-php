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

use Elastic\OTel\Log\RemoteConfigLoggingLevel;
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

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L48
     */
    public const LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME = 'logging_level';

    /**
     * @see \OpenTelemetry\API\Behavior\Internal\Logging::OTEL_LOG_LEVEL
     */
    public const OTEL_LOG_LEVEL_OPTION_NAME = 'OTEL_LOG_LEVEL';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.2.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L106
     */
    public const SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME = 'sampling_rate';

    /** @var ?ElasticFileDecodedBody */
    private static ?array $lastAppliedElasticFileDecodedBody = null;

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
    /**
     * @param callable(mixed): bool $predicate
     */
    private static function verifyValueType(string $remoteOptName, mixed $remoteOptVal, string $dbgTypeDesc, callable $predicate): bool
    {
        if ($predicate($remoteOptVal)) {
            return true;
        }

        self::logError(
            "Remote config option value type is not as expected; option name: $remoteOptName ; actual value type: " . get_debug_type($remoteOptVal) . "; expected value type: $dbgTypeDesc",
            __LINE__,
            __FUNCTION__,
        );
        return false;
    }

    private static function verifyValueIsString(string $remoteOptName, mixed $remoteOptVal): bool
    {
        return self::verifyValueType($remoteOptName, $remoteOptVal, 'string', is_string(...));
    }

    private static function verifyValueIsJsonFloat(string $remoteOptName, mixed $remoteOptVal): bool
    {
        return self::verifyValueType($remoteOptName, $remoteOptVal, 'float', is_numeric(...));
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

        $remoteConfigLoggingLevel = RemoteConfigLoggingLevel::tryToFindByName($remoteOptVal);
        if ($remoteConfigLoggingLevel === null) {
            self::logError(
                'Option ' . self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME . " value is not in the set of the expected values: $remoteOptVal",
                __LINE__,
                __FUNCTION__
            );
            return;
        }
        $otelLogLevel = $remoteConfigLoggingLevel->toOTelInternalLogLevel()->name;

        /**
         * OTel SDK reads log level config directly from $_SERVER
         * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L72
         */
        $_SERVER[self::OTEL_LOG_LEVEL_OPTION_NAME] = $otelLogLevel;
        self::logDebug('Set OTel SDK log level to ' . $otelLogLevel, __LINE__, __FUNCTION__);
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
        if (!self::verifyValueIsJsonFloat(self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME, $remoteOptVal)) {
            return;
        }
        /** @var float|int|numeric-string $remoteOptVal */
        $remoteOptValAsFloat = floatval($remoteOptVal);

        if (!self::isInClosedRange(0, $remoteOptValAsFloat, 1)) {
            self::logError(
                'Option ' . self::SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME . " value is not between 0 and 1: $remoteOptValAsFloat",
                __LINE__,
                __FUNCTION__
            );
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

        PhpPartFacade::setEnvVar(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER, OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO);
        PhpPartFacade::setEnvVar(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG, strval($remoteOptValAsFloat));
        self::logDebug('Set OTel SDK sampling rate to ' . $remoteOptValAsFloat, __LINE__, __FUNCTION__);
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
            self::LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME => self::parseAndApplyLoggingLevel($remoteOptVal),
            self::SAMPLING_RATE_REMOTE_CONFIG_OPTION_NAME => self::parseAndApplySamplingRate($remoteOptVal),
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
     * @return ?ElasticFileDecodedBody
     */
    public static function getLastAppliedElasticFileDecodedBody(): ?array
    {
        return self::$lastAppliedElasticFileDecodedBody;
    }

    /**
     * @param ElasticFileDecodedBody $remoteCfgOptKeyToValMap
     */
    private static function parseAndApplyOptionNameToValueMap(array $remoteCfgOptKeyToValMap): void
    {
        self::$lastAppliedElasticFileDecodedBody = $remoteCfgOptKeyToValMap;

        foreach ($remoteCfgOptKeyToValMap as $remoteOptName => $remoteOptVal) {
            self::parseAndApplyOption($remoteOptName, $remoteOptVal);
        }
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
