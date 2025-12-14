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

use OpenTelemetry\API\Behavior\Internal\Logging;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\API\Behavior\Internal\LogWriter\LogWriterInterface;

class ElasticLogWriter implements LogWriterInterface
{
    private bool $attachLogContext;

    public function __construct()
    {
        $this->attachLogContext = Configuration::getBoolean('ELASTIC_OTEL_LOG_OTEL_WITH_CONTEXT', true);
    }

    private static function levelToEdot(mixed $level): ?LogLevel
    {
        if (!is_string($level)) {
            return null;
        }

        if (($psrLevel = PsrLogLevel::tryFindByString($level)) === null) {
            return null;
        }

        return LogLevel::fromPsrLevel($psrLevel);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function write(mixed $level, string $message, array $context): void
    {
        $edotLevel = self::levelToEdot($level) ?? LogLevel::off;

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)[3];

        $func = ($caller['class'] ?? '') . ($caller['type'] ?? '') . $caller['function'];
        $logContext = $this->attachLogContext ? (' context: ' . var_export($context, true)) : '';

        elastic_otel_log_feature(
            0 /* <- isForced */,
            $edotLevel->value,
            LogFeature::OTEL,
            $caller['file'] ?? '',
            $caller['line'] ?? null,
            $func,
            $message . $logContext
        );
    }

    public static function enableLogWriter(): void
    {
        Logging::setLogWriter(new self());
    }
}
