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
use PHPUnit\Framework\Assert;

/**
 * @template T
 */
final class Optional implements LoggableInterface
{
    /** @var T */
    private mixed $value;
    private bool $isValueSet = false;

    /**
     * @return T
     */
    public function getValue()
    {
        Assert::assertTrue($this->isValueSet);
        return $this->value;
    }

    /**
     * @param T $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
        $this->isValueSet = true;
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

    public function reset(): void
    {
        $this->isValueSet = false;
        unset($this->value);
    }

    public function isValueSet(): bool
    {
        return $this->isValueSet;
    }

    /**
     * @param T $value
     *
     * @noinspection PhpUnused
     */
    public function setValueIfNotSet($value): void
    {
        if (!$this->isValueSet) {
            $this->setValue($value);
        }
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->isValueSet ? $this->value : /** @lang text */ '<Optional NOT SET>');
    }
}
