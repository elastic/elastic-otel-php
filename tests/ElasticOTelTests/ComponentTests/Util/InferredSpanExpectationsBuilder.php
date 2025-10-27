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

use ElasticOTelTests\ComponentTests\Util\OtlpData\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class InferredSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public const IS_INFERRED_ATTRIBUTE_NAME = 'is_inferred';

    public function __construct()
    {
        parent::__construct();

        $this->kind(SpanKind::internal)
             ->addAttribute(self::IS_INFERRED_ATTRIBUTE_NAME, true);
    }

    private static function buildFor(self $builderClone, StackTraceExpectations $stackTrace, ?int $codeLineNumber): SpanExpectations
    {
        if ($codeLineNumber !== null) {
            $builderClone->addAttribute(TraceAttributes::CODE_LINE_NUMBER, $codeLineNumber);
        }
        return $builderClone->stackTrace($stackTrace)->build();
    }

    public function buildForStaticMethod(string $className, string $methodName, StackTraceExpectations $stackTrace, ?int $codeLineNumber = null): SpanExpectations
    {
        return self::buildFor((clone $this)->nameAndCodeAttributesUsingClassMethod($className, $methodName, isStaticMethod: true), $stackTrace, $codeLineNumber);
    }

    public function buildForFunction(string $funcName, StackTraceExpectations $stackTrace, ?int $codeLineNumber = null): SpanExpectations
    {
        return self::buildFor((clone $this)->nameAndCodeAttributesUsingFuncName($funcName), $stackTrace, $codeLineNumber);
    }
}
