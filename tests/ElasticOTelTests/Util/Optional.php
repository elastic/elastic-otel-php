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
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Override;
use PHPUnit\Framework\Assert;

/**
 * @template T
 */
final class Optional implements LoggableInterface
{
    /**
     * @param T $value
     */
    private function __construct(
        public readonly mixed $value,
        public readonly bool $isValueSet = true
    ) {
    }

    /**
     * @param T $value
     *
     * @return self<T>
     */
    public static function value(mixed $value): self
    {
        return new self($value);
    }

    /**
     * @return self<T>
     */
    public static function none(): self
    {
        static $cached = null;
        return $cached ??= new self(value: null, isValueSet: false); // @phpstan-ignore return.type
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        Assert::assertTrue($this->isValueSet);
        return $this->value;
    }

    /**
     * @param T $elseValue
     *
     * @return T
     *
     * @noinspection PhpUnused
     */
    public function getValueOr($elseValue)
    {
        return $this->isValueSet ? $this->value : $elseValue;
    }

    public function isValueSet(): bool
    {
        return $this->isValueSet;
    }

    /**
     * @param T $value
     *
     * @return self<T>
     *
     * @noinspection PhpUnused
     */
    public function valueIfNotSet($value): self
    {
        return $this->isValueSet ? $this : Optional::value($value);
    }

    #[Override]
    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->isValueSet ? $this->value : /** @lang text */ '<Optional NOT SET>');
    }
}
