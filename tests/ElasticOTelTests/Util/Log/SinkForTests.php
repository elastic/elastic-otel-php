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

use DateTime;
use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\Util\TextUtilForTests;

final class SinkForTests extends SinkBase
{
    public function __construct(
        private readonly string $dbgProcessName
    ) {
    }

    protected function consumePreformatted(
        LogLevel $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void {
        $formattedRecord = '[Elastic OTel PHP tests]';
        $formattedRecord .= ' ' . (new DateTime())->format('Y-m-d H:i:s.v P');
        $formattedRecord .= ' [' . strtoupper($statementLevel->name) . ']';
        $formattedRecord .= ' [PID: ' . getmypid() . ']';
        $formattedRecord .= ' [' . $this->dbgProcessName . ']';
        $formattedRecord .= ' [' . basename($srcCodeFile) . ':' . $srcCodeLine . ']';
        $formattedRecord .= ' [' . $srcCodeFunc . ']';
        $formattedRecord .= TextUtilForTests::combineWithSeparatorIfNotEmpty(' ', $messageWithContext);
        $this->consumeFormatted($statementLevel, $formattedRecord);
    }

    public static function writeLineToStdErr(string $text): void
    {
        StdError::singletonInstance()->writeLine($text);
    }

    private function consumeFormatted(LogLevel $statementLevel, string $statementText): void
    {
        syslog(self::levelToSyslog($statementLevel), $statementText);
        self::writeLineToStdErr($statementText);
    }

    private static function levelToSyslog(LogLevel $level): int
    {
        return match ($level) {
            LogLevel::off, LogLevel::critical => LOG_CRIT,
            LogLevel::error => LOG_ERR,
            LogLevel::warning => LOG_WARNING,
            LogLevel::info => LOG_INFO,
            LogLevel::debug, LogLevel::trace => LOG_DEBUG
        };
    }
}
