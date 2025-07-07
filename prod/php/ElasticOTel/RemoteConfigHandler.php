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

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RemoteConfigHandler
{
    use StaticClassTrait;

    private const REMOTE_CONFIG_FILE_NAME = 'elastic';

    private const REMOTE_CONFIG_OPTION_NAME_TO_VALUE = ['logging_level' => 'OTEL_LOG_LEVEL'];

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

    private static function parseAndApply(string $remoteConfigContent): void
    {
        if (($remoteOptNameToVal = self::decodeRemoteConfig($remoteConfigContent)) === null) {
            return;
        }

        foreach ($remoteOptNameToVal as $remoteOptName => $remoteOptVal) {
            if (!ArrayUtil::getValueIfKeyExists($remoteOptName, self::REMOTE_CONFIG_OPTION_NAME_TO_VALUE, /* out */ $envVarName)) {
                self::logDebug(
                    'Encountered an option that is not supported as remote configuration option'
                    . '; option name: ' . $remoteOptName
                    . '; value type: ' . get_debug_type($remoteOptVal)
                    . '; value: ' . $remoteOptVal,
                    __LINE__,
                    __FUNCTION__
                );
                continue;
            }
            if (!is_scalar($remoteOptVal)) {
                self::logError('Remote config value is not a scalar; remoteOptName: ' . $remoteOptName . '; value type: ' . get_debug_type($remoteOptVal), __LINE__, __FUNCTION__);
                continue;
            }
            if (!putenv($envVarName . '=' . $remoteOptVal)) {
                self::logDebug('putenv returned false; env var: name: ' . $envVarName . '; value: ' . $remoteOptVal, __LINE__, __FUNCTION__);
            }
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
    public static function decodeRemoteConfig(string $remoteConfigContent): ?array
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
