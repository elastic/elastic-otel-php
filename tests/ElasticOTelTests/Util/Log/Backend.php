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
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Backend implements LoggableInterface
{
    public const NAMESPACE_KEY = 'namespace';
    public const CLASS_KEY = 'class';

    private LogLevel $maxEnabledLevel;

    private SinkInterface $logSink;

    public function __construct(LogLevel $maxEnabledLevel, ?SinkInterface $logSink)
    {
        $this->maxEnabledLevel = $maxEnabledLevel;
        $this->logSink = $logSink ?? NoopLogSink::singletonInstance();
    }

    public function isEnabledForLevel(LogLevel $level): bool
    {
        return $this->maxEnabledLevel->value >= $level->value;
    }

    public function clone(): self
    {
        return new self($this->maxEnabledLevel, $this->logSink);
    }

    public function setMaxEnabledLevel(LogLevel $maxEnabledLevel): void
    {
        $this->maxEnabledLevel = $maxEnabledLevel;
    }

    /**
     * @param array<array-key, mixed> $statementCtx
     *
     * @return array<array-key, mixed>
     */
    private static function mergeContexts(LoggerData $loggerData, array $statementCtx): array
    {
        /**
         * @see Comment in \ElasticOTelTests\Util\Log\Logger::addAllContext regarding the order of entries in logger context
         */

        $result = $statementCtx;

        $mergeKeyValueToResult = function (string|int $key, mixed $value) use (&$result): void {
            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        };

        for (
            $currentLoggerData = $loggerData;
            $currentLoggerData !== null;
            $currentLoggerData = $currentLoggerData->inheritedData
        ) {
            foreach (ArrayUtilForTests::iterateMapInReverse($currentLoggerData->context) as $key => $value) {
                $mergeKeyValueToResult($key, $value);
            }
        }

        $mergeKeyValueToResult(self::NAMESPACE_KEY, $loggerData->namespace);
        $mergeKeyValueToResult(self::CLASS_KEY, ClassNameUtil::fqToShort($loggerData->fqClassName));

        return $result;
    }

    /**
     * @param array<string, mixed> $statementCtx
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public function log(
        LogLevel $statementLevel,
        string $message,
        array $statementCtx,
        int $srcCodeLine,
        string $srcCodeFunc,
        LoggerData $loggerData,
        ?bool $includeStacktrace,
        int $numberOfStackFramesToSkip
    ): void {
        $this->logSink->consume(
            $statementLevel,
            $message,
            self::mergeContexts($loggerData, $statementCtx),
            $loggerData->category,
            $loggerData->srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $includeStacktrace,
            $numberOfStackFramesToSkip + 1
        );
    }

    /** @noinspection PhpUnused */
    public function getSink(): SinkInterface
    {
        return $this->logSink;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(['maxEnabledLevel' => $this->maxEnabledLevel, 'logSink' => DbgUtil::getType($this->logSink)]);
    }
}
