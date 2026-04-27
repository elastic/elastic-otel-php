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
 * @see \Psr\Log\LogLevel
 */
enum PsrLogLevel
{
    use EnumUtilTrait;

    // const can be used as value for enum case only from PHP 8.2
    // so const from Psr\Log\LogLevel cannot be used directly until we drop PHP 8.1 support
    // so match between cases names below and Psr\Log\LogLevel::* const's are asserted by tests

    case emergency;
    case alert;
    case critical;
    case error;
    case warning;
    case notice;
    case info;
    case debug;

    public function toElasticLogLevel(): LogLevel
    {
        return match ($this) {
            self::emergency, self::alert, self::critical => LogLevel::critical,
            self::error => LogLevel::error,
            self::warning => LogLevel::warning,
            self::notice, self::info => LogLevel::info,
            self::debug => LogLevel::debug,
        };
    }
}
