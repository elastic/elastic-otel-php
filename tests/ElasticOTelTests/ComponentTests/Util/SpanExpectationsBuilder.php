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

class SpanExpectationsBuilder
{
    private const CLASS_AND_METHOD_SEPARATOR = '::';

    protected ?string $name = null;
    protected ?SpanKind $kind = null;
    protected ?SpanAttributesExpectations $attributes = null;

    /**
     * @return $this
     */
    public function setNameUsingClassMethod(string $className, ?bool $isStaticMethod, string $methodName): self
    {
        $this->name = self::buildNameFromClassMethod($className, $isStaticMethod, $methodName);
        return $this;
    }

    /**
     * @return $this
     */
    public function setNameUsingFuncName(string $funcName): self
    {
        $this->name = $funcName;
        return $this;
    }

    /**
     * @return $this
     */
    public function setKind(?SpanKind $kind): self
    {
        $this->kind = $kind;
        return $this;
    }

    /**
     * @return $this
     */
    public function setAttributes(SpanAttributesExpectations $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function buildNameFromClassMethod(?string $classicName, ?bool $isStaticMethod, ?string $methodName): ?string
    {
        if ($methodName === null) {
            return null;
        }

        if ($classicName === null) {
            return $methodName;
        }

        return $classicName . self::CLASS_AND_METHOD_SEPARATOR . $methodName;
    }

    public function build(): SpanExpectations
    {
        return new SpanExpectations($this->name, $this->kind, $this->attributes);
    }
}
