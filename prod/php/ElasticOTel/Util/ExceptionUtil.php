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

namespace Elastic\OTel\Util;

use Elastic\OTel\Log\AdhocLoggableObject;
use Elastic\OTel\Log\LoggableStackTrace;
use Elastic\OTel\Log\LoggableToString;
use Elastic\OTel\Log\PropertyLogPriority;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ExceptionUtil
{
    use StaticClassTrait;

    /**
     * @param string               $messagePrefix
     * @param array<string, mixed> $context
     * @param ?int                 $numberOfStackFramesToSkip PHP_INT_MAX means no stack trace
     *
     * @return string
     *
     * @phpstan-param null|0|positive-int $numberOfStackFramesToSkip
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    public static function buildMessage(string $messagePrefix, array $context = [], ?int $numberOfStackFramesToSkip = null): string
    {
        $messageSuffixObj = new AdhocLoggableObject($context);
        if ($numberOfStackFramesToSkip !== null) {
            $stacktrace = LoggableStackTrace::buildForCurrent($numberOfStackFramesToSkip + 1);
            $messageSuffixObj->addProperties([LoggableStackTrace::STACK_TRACE_KEY => $stacktrace], PropertyLogPriority::MUST_BE_INCLUDED);
        }
        $messageSuffix = LoggableToString::convert($messageSuffixObj, /* prettyPrint */ true);
        return $messagePrefix . (TextUtil::isEmptyString($messageSuffix) ? '' : ('. ' . $messageSuffix));
    }
}