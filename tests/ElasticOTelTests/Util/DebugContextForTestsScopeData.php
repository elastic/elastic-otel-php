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

namespace ElasticOTelTests\Util;

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use PHPUnit\Framework\Assert;

final class DebugContextForTestsScopeData implements LoggableInterface
{
    use LoggableTrait;

    /** @var Pair<string, array<string, mixed>>[] */
    public array $subScopesStack;

    /**
     * @param string               $name
     * @param array<string, mixed> $initialCtx
     */
    public function __construct(string $name, array $initialCtx)
    {
        $this->subScopesStack = [new Pair($name, $initialCtx)];
    }

    /**
     * @param int $numberOfStackFramesToSkip
     *
     * @return string
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public static function buildContextName(int $numberOfStackFramesToSkip): string
    {
        $callerInfo = DbgUtil::getCallerInfoFromStacktrace($numberOfStackFramesToSkip + 1);

        $classMethodPart = '';
        if ($callerInfo->class !== null) {
            $classMethodPart .= $callerInfo->class . '::';
        }
        Assert::assertNotNull($callerInfo->function);
        $classMethodPart .= $callerInfo->function;

        $fileLinePart = '';
        if ($callerInfo->file !== null) {
            $fileLinePart .= '[';
            $fileLinePart .= $callerInfo->file;
            $fileLinePart .= TextUtilForTests::combineWithSeparatorIfNotEmpty(':', TextUtilForTests::strvalEmptyIfNull($callerInfo->line));
            $fileLinePart .= ']';
        }

        return $classMethodPart . TextUtilForTests::combineWithSeparatorIfNotEmpty(' ', $fileLinePart);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $name = ArrayUtilForTests::isEmpty($this->subScopesStack) ? 'N/A' : ArrayUtilForTests::getFirstValue($this->subScopesStack)->first;
        $stream->toLogAs(['name' => $name, 'subScopesStack count' => count($this->subScopesStack)]);
    }
}
