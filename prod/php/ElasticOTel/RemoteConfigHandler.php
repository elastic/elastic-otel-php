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
use Elastic\OTel\Util\StaticClassTrait;
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

    public const OTEL_EXPERIMENTAL_CONFIG_FILE = 'OTEL_EXPERIMENTAL_CONFIG_FILE';

    /**
     * Should be the same as the string used by Kibana
     * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L48
     */
    public const LOGGING_LEVEL_REMOTE_CONFIG_OPTION_NAME = 'logging_level';
    public const LOG_LEVEL_OTEL_OPTION_NAME = 'OTEL_LOG_LEVEL';
    public const OTEL_LOG_LEVEL_NONE = 'none';

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function fetchAndApply(): void
    {
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

    /**
     * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L72
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
        }

        /**
         * OTel SDK reads log level config directly from $_SERVER
         * @see https://github.com/open-telemetry/opentelemetry-php/blob/73ff5adcb8f1db348bedb422de760e475df16841/src/API/Behavior/Internal/Logging.php#L72
         */
        $_SERVER[self::LOG_LEVEL_OTEL_OPTION_NAME] = $otelLogLevel;
        self::logDebug('Set OTel SDK log level to ' . $otelLogLevel, __LINE__, __FUNCTION__);
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

    private static function parseAndApply(string $remoteConfigContent): void
    {
        if (($remoteOptNameToVal = self::decodeRemoteConfig($remoteConfigContent)) === null) {
            return;
        }

        foreach ($remoteOptNameToVal as $remoteOptName => $remoteOptVal) {
            self::parseAndApplyOption($remoteOptName, $remoteOptVal);
        }
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
