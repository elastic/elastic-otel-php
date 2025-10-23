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

use Elastic\OTel\Util\SingletonInstanceTrait;

/**
 * @phpstan-import-type ConfigStore from DebugContext
 */
final class DebugContextConfig
{
    use SingletonInstanceTrait;

    public const ENABLED_OPTION_NAME = 'enabled';
    public const ENABLED_DEFAULT_VALUE = true;
    public const ADD_TO_ASSERTION_MESSAGE_OPTION_NAME = 'add_to_assertion_message';
    public const ADD_TO_ASSERTION_MESSAGE_DEFAULT_VALUE = true;
    public const AUTO_CAPTURE_THIS_OPTION_NAME = 'auto_capture_this';
    public const AUTO_CAPTURE_THIS_DEFAULT_VALUE = true;
    public const AUTO_CAPTURE_ARGS_OPTION_NAME = 'auto_capture_args';
    public const AUTO_CAPTURE_ARGS_DEFAULT_VALUE = true;
    public const ONLY_ADDED_CONTEXT_OPTION_NAME = 'only_added_context';
    public const ONLY_ADDED_CONTEXT_DEFAULT_VALUE = false;
    public const TRIM_VENDOR_FRAMES_OPTION_NAME = 'trim_vendor_frames';
    public const TRIM_VENDOR_FRAMES_DEFAULT_VALUE = true;
    public const DEFAULT_VALUES
        = [
            self::ENABLED_OPTION_NAME                  => self::ENABLED_DEFAULT_VALUE,
            self::ADD_TO_ASSERTION_MESSAGE_OPTION_NAME => self::ADD_TO_ASSERTION_MESSAGE_DEFAULT_VALUE,
            self::AUTO_CAPTURE_THIS_OPTION_NAME        => self::AUTO_CAPTURE_THIS_DEFAULT_VALUE,
            self::AUTO_CAPTURE_ARGS_OPTION_NAME        => self::AUTO_CAPTURE_ARGS_DEFAULT_VALUE,
            self::ONLY_ADDED_CONTEXT_OPTION_NAME       => self::ONLY_ADDED_CONTEXT_DEFAULT_VALUE,
            self::TRIM_VENDOR_FRAMES_OPTION_NAME       => self::TRIM_VENDOR_FRAMES_DEFAULT_VALUE,
        ];

    /**
     * @return ConfigStore
     */
    public static function getCopy(): array
    {
        return DebugContextSingleton::singletonInstance()->getConfigCopy();
    }

    /**
     * @param ConfigStore $config
     */
    public static function set(array $config): void
    {
        DebugContextSingleton::singletonInstance()->setConfig($config);
    }

    public static function enabled(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::ENABLED_OPTION_NAME, $newValue);
    }

    public static function addToAssertionMessage(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::ADD_TO_ASSERTION_MESSAGE_OPTION_NAME, $newValue);
    }

    public static function onlyAddedContext(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::ONLY_ADDED_CONTEXT_OPTION_NAME, $newValue);
    }

    public static function autoCaptureThis(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::AUTO_CAPTURE_THIS_OPTION_NAME, $newValue);
    }

    public static function autoCaptureArgs(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::AUTO_CAPTURE_ARGS_OPTION_NAME, $newValue);
    }

    public static function trimVendorFrames(?bool $newValue = null): bool
    {
        return DebugContextSingleton::singletonInstance()->readWriteConfigOption(self::TRIM_VENDOR_FRAMES_OPTION_NAME, $newValue);
    }
}
