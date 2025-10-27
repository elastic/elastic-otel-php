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

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use ElasticOTelTests\Util\MonotonicTime;
use ElasticOTelTests\Util\SystemTime;

/**
 * @phpstan-type HttpHeaders array<string, string[]>
 */
final class IntakeDataRequestRaw extends AgentBackendCommEvent
{
    use LoggableTrait;

    /**
     * @param HttpHeaders $httpHeaders
     */
    public function __construct(
        MonotonicTime $monotonicTime,
        SystemTime $systemTime,
        public readonly OTelSignalType $signalType,
        public readonly array $httpHeaders,
        public readonly string $body,
    ) {
        parent::__construct($monotonicTime, $systemTime);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['body'];
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $customToLog = ['strlen(body)' => strlen($this->body)];
        $this->toLogLoggableTraitImpl($stream, $customToLog);
    }
}
