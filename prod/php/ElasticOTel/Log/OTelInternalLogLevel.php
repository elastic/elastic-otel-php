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
use RuntimeException;

/**
 * Values used by OTel SDK:
 * @see \OpenTelemetry\API\Behavior\Internal\Logging
 * @link https://github.com/open-telemetry/opentelemetry-php/blob/4913926ec1bb2a10118174da543e823ec8df3fc7/src/API/Behavior/Internal/Logging.php#L19
 *
 * It's essentially PSR-3 log spec + none (for switching the logging off)
 *
 * Also:
 * @see https://github.com/php-fig/log/blob/1.1.0/Psr/Log/LogLevel.php
 * @see https://github.com/php-fig/log/blob/3.0.2/src/LogLevel.php
 */
enum OTelInternalLogLevel
{
    use EnumUtilTrait;

    case debug;
    case info;
    case notice;
    case warning;
    case error;
    case critical;
    case alert;
    case emergency;
    case none;

    public function toElasticLogLevel(): LogLevel
    {
        if ($this === self::none) {
            return LogLevel::off;
        }

        if (($psrLogLevel = PsrLogLevel::tryToFindByName($this->name)) === null) {
            throw new RuntimeException('Unexpected ' . __CLASS__ . ' value that does not have a case with the same name in ' . PsrLogLevel::class);
        }
        return $psrLogLevel->toElasticLogLevel();
    }
}
