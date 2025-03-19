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

use ElasticOTelTests\Util\StackTraceUtil;

final class StackTraceFrameExpectationsBuilder
{
    protected NullableStringExpectations $file;

    /** @var LeafExpectations<?positive-int> */
    protected LeafExpectations $line;

    protected NullableStringExpectations $function;

    public function __construct()
    {
        $this->file = NullableStringExpectations::matchAny();
        $this->line = LeafExpectations::matchAny(); // @phpstan-ignore assign.propertyType
        $this->function = NullableStringExpectations::matchAny();
    }

    /**
     * @return $this
     */
    public function file(string $file): self
    {
        $this->file = NullableStringExpectations::literal($file);
        return $this;
    }

    /**
     * @param positive-int $line
     *
     * @return $this
     */
    public function line(int $line): self
    {
        $this->line = LeafExpectations::expectedValue($line); // @phpstan-ignore assign.propertyType
        return $this;
    }

    /**
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function noFileLine(): self
    {
        $this->file = NullableStringExpectations::literal(null);
        $this->line = LeafExpectations::expectedValue(null); // @phpstan-ignore assign.propertyType
        return $this;
    }

    /**
     * @return $this
     */
    public function function(string $funcName): self
    {
        $this->function = NullableStringExpectations::literal($funcName);
        return $this;
    }

    /**
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function staticClassMethod(string $className, string $methodName): self
    {
        return $this->function($className . StackTraceUtil::METHOD_IS_STATIC_KIND_VALUE . $methodName);
    }

    /**
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function instanceClassMethod(string $className, string $methodName): self
    {
        return $this->function($className . StackTraceUtil::METHOD_IS_INSTANCE_KIND_VALUE . $methodName);
    }

    /**
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function functionRegEx(string $functionRegEx): self
    {
        $this->function = NullableStringExpectations::regex($functionRegEx);
        return $this;
    }

    public function build(): StackTraceFrameExpectations
    {
        return new StackTraceFrameExpectations($this->file, $this->line, $this->function);
    }
}
