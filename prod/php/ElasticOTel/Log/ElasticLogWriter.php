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

namespace Elastic\OTel\Log;

use OpenTelemetry\API\Behavior\Internal\LogWriter\LogWriterInterface;
use OpenTelemetry\API\Behavior\Internal\Logging;

class ElasticLogWriter implements LogWriterInterface
{
    /**
     * @param array<mixed> $context
     */
    public function write(mixed $level, string $message, array $context): void
    {
        /**
         * elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedClassInspection, PhpUndefinedFunctionInspection
         */
        \elastic_otel_log_feature( // @phpstan-ignore function.notFound
            0 /* isForced */,
            Level::getFromPsrLevel(strval($level)) /* level */, // @phpstan-ignore argument.type
            LogFeature::OTEL /* feature */, // @phpstan-ignore class.notFound
            '' /* category */,
            '' /* file */,
            0 /* line */,
            $context['source'] ?? '' /* func */,
            $message . ' context: ' . var_export($context, true) /* message */
        );
    }

    public static function enableLogWriter(): void
    {
        Logging::setLogWriter(new self());
    }
}
