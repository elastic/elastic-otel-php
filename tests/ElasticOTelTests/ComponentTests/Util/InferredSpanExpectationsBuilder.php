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

class InferredSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public const IS_INFERRED_ATTRIBUTE_NAME = 'is_inferred';

    public function __construct()
    {
        $this->setKind(SpanKind::internal);
        $this->addAttribute(self::IS_INFERRED_ATTRIBUTE_NAME, true);
    }

    public function forClassMethod(string $className, string $methodName, ?bool $isStaticMethod = null): SpanExpectations
    {
        return (clone $this)->setNameAndCodeAttributesUsingClassMethod($className, $methodName, $isStaticMethod)->build();
    }

    public function forStandaloneFunction(string $funcName): SpanExpectations
    {
        return (clone $this)->setNameAndCodeAttributesUsingFuncName($funcName)->build();
    }
}
