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

namespace ElasticOTelTests\Util\Log;

use Elastic\OTel\Log\LogLevel;
use Throwable;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledLoggerProxy
{
    private ?bool $includeStackTrace = null;

    public function __construct(
        private readonly LogLevel $statementLevel,
        private readonly int $srcCodeLine,
        private readonly string $srcCodeFunc,
        private readonly LoggerData $loggerData
    ) {
    }

    public function includeStackTrace(bool $shouldIncludeStackTrace = true): self
    {
        $this->includeStackTrace = $shouldIncludeStackTrace;
        return $this;
    }

    /**
     * @param array<string, mixed> $statementCtx
     */
    public function log(string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx,
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            $this->includeStackTrace,
            numberOfStackFramesToSkip: 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }

    /**
     * @param array<string, mixed> $statementCtx
     *
     * @noinspection PhpUnused
     */
    public function logThrowable(Throwable $throwable, string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx + ['throwable' => $throwable],
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            $this->includeStackTrace,
            numberOfStackFramesToSkip: 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }
}
