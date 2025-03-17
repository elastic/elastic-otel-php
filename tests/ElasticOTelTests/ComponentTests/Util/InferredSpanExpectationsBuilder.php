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

use OpenTelemetry\SemConv\TraceAttributes;

/**
 * @phpstan-import-type AttributeValue from SpanAttributes
 */
class InferredSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public const IS_INFERRED_ATTRIBUTE_NAME = 'is_inferred';

    public function __construct()
    {
        $this->setKind(SpanKind::internal);
        $this->addAllowedAttribute(self::IS_INFERRED_ATTRIBUTE_NAME, true);
    }

    /**
     * @phpstan-param AttributeValue $value
     */
    private function addAllowedAttribute(string $key, array|bool|float|int|null|string $value): void
    {
        $prevAttributesExpectations = $this->attributes ?? (new SpanAttributesExpectations(attributes: []));
        $this->setAttributes($prevAttributesExpectations->addAllowedAttribute($key, $value));
    }

    /**
     * @return $this
     */
    public function setNameUsingFuncName(string $funcName): self
    {
        $this->addAllowedAttribute(TraceAttributes::CODE_FUNCTION_NAME, $funcName);
        return parent::setNameUsingFuncName($funcName);
    }
}
