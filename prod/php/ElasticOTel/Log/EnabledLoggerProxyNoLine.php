<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledLoggerProxyNoLine
{
    /** @var int */
    private $statementLevel;

    /** @var string */
    private $srcCodeFunc;

    /** @var LoggerData */
    private $loggerData;

    public function __construct(int $statementLevel, string $srcCodeFunc, LoggerData $loggerData)
    {
        $this->statementLevel = $statementLevel;
        $this->srcCodeFunc = $srcCodeFunc;
        $this->loggerData = $loggerData;
    }

    /**
     * @param int                  $srcCodeLine
     * @param string               $message
     * @param array<string, mixed> $statementCtx
     *
     * @return bool
     */
    public function log(int $srcCodeLine, string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx,
            $srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            null /* <- includeStackTrace */,
            1 /* <- numberOfStackFramesToSkip */
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }
}
