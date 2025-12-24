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

namespace Elastic\OTel\Log;

use Elastic\OTel\Util\EnumUtilTrait;

/**
 * Values used by Remote/Central Configuration:
 * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts#L59
 */
enum RemoteConfigLoggingLevel
{
    use EnumUtilTrait;

    case trace;
    case debug;
    case info;
    case warn;
    case error;
    case fatal;
    case off;

    public function toOTelInternalLogLevel(): OTelInternalLogLevel
    {
        return match ($this) {
            self::trace, self::debug => OTelInternalLogLevel::debug,
            self::info => OTelInternalLogLevel::info,
            self::warn => OTelInternalLogLevel::warning,
            self::error => OTelInternalLogLevel::error,
            self::fatal => OTelInternalLogLevel::critical,
            self::off => OTelInternalLogLevel::none,
        };
    }

    public function toElasticLogLevel(): LogLevel
    {
        return match ($this) {
            self::trace => LogLevel::trace,
            self::debug => LogLevel::debug,
            self::info => LogLevel::info,
            self::warn => LogLevel::warning,
            self::error => LogLevel::error,
            self::fatal => LogLevel::critical,
            self::off => LogLevel::off,
        };
    }
}
