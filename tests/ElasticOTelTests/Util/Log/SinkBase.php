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
use Elastic\OTel\Util\TextUtil;
use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class SinkBase implements SinkInterface
{
    /** @inheritDoc */
    #[Override]
    public function consume(
        LogLevel $statementLevel,
        string $message,
        array $context,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        ?bool $includeStacktrace,
        int $numberOfStackFramesToSkip
    ): void {
        if ($includeStacktrace === null ? ($statementLevel <= LogLevel::error) : $includeStacktrace) {
            $context[LoggableStackTrace::STACK_TRACE_KEY] = LoggableStackTrace::buildForCurrent($numberOfStackFramesToSkip + 1);
        }

        $ctxAsStr = LoggableToString::convert($context);
        $msgCtxSeparator = (TextUtil::isEmptyString($message) || TextUtil::isEmptyString($ctxAsStr)) ? '' : ' ';
        $messageWithContext = $message . $msgCtxSeparator . $ctxAsStr;

        $this->consumePreformatted(
            $statementLevel,
            $category,
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $messageWithContext
        );
    }

    abstract protected function consumePreformatted(
        LogLevel $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void;
}
