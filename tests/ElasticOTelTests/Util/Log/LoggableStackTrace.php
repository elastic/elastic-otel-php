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

use ElasticOTelTests\Util\ClassicFormatStackTraceFrame;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\StackTraceUtil;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableStackTrace
{
    public const STACK_TRACE_KEY = 'stacktrace';

    public const MAX_NUMBER_OF_STACK_FRAMES = 100;

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     * @param ?positive-int    $maxNumberOfStackFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function buildForCurrent(int $numberOfStackFramesToSkip, ?int $maxNumberOfStackFrames = self::MAX_NUMBER_OF_STACK_FRAMES): array
    {
        $capturedFrames = (new StackTraceUtil(NoopLoggerFactory::singletonInstance()))->captureInClassicFormat($numberOfStackFramesToSkip + 1, $maxNumberOfStackFrames);
        /** @var ClassicFormatStackTraceFrame[] $result */
        $result = [];

        foreach ($capturedFrames as $capturedFrame) {
            $result[] = new ClassicFormatStackTraceFrame(
                self::adaptSourceCodeFilePath($capturedFrame->file),
                $capturedFrame->line,
                ($capturedFrame->class === null) ? null : ClassNameUtil::fqToShortFromRawString($capturedFrame->class),
                $capturedFrame->isStaticMethod,
                $capturedFrame->function
            );
        }
        return $result;
    }

    public static function adaptSourceCodeFilePath(?string $srcFile): ?string
    {
        return $srcFile === null ? null : basename($srcFile);
    }
}
