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

use Countable;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use IteratorAggregate;
use OutOfBoundsException;
use Override;
use Traversable;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template T
 *
 * @implements IteratorAggregate<T>
 */
final class ListSlice implements Countable, IteratorAggregate, LoggableInterface
{
    /** @var array<T> */
    public readonly array $base;

    /** @var non-negative-int */
    public readonly int $offset;

    /** @var non-negative-int */
    public readonly int $length;

    /**
     * @param array<T>          $base
     * @param non-negative-int  $offset
     * @param ?non-negative-int $length
     */
    public function __construct(array $base, int $offset = 0, ?int $length = null)
    {
        $this->base = $base;
        $this->offset = $offset;
        $baseLength = count($base);
        if ($length === null) {
            if ($offset > $baseLength) {
                throw new OutOfBoundsException(ExceptionUtil::buildMessage('offset > baseLength', compact('baseLength', 'offset', 'base')));
            }
            $this->length = $baseLength - $offset; // @phpstan-ignore assign.propertyType
        } else {
            if ($offset + $length > $baseLength) {
                throw new OutOfBoundsException(ExceptionUtil::buildMessage('offset + length > baseLength', compact('baseLength', 'offset', 'length', 'base')));
            }
            $this->length = $length;
        }
    }

    #[Override]
    public function count(): int
    {
        return $this->length; // @phpstan-ignore return.type
    }

    #[Override]
    public function getIterator(): Traversable
    {
        foreach (RangeUtil::generateUpTo($this->length) as $i) {
            yield $this->base[$this->offset + $i];
        }
    }

    /**
     * @return self<T>
     */
    public function clone(): self
    {
        return new self($this->base, $this->offset, $this->length); // @phpstan-ignore argument.type
    }

    /**
     * @param non-negative-int $prefixLength
     *
     * @return self<T>
     */
    public function withoutPrefix(int $prefixLength): self
    {
        if ($prefixLength > $this->length) {
            throw new OutOfBoundsException(ExceptionUtil::buildMessage('prefixLength is larger than length', compact('prefixLength', 'this')));
        }
        return new self($this->base, $this->offset + $prefixLength, $this->length - $prefixLength); // @phpstan-ignore argument.type
    }

    /**
     * @param non-negative-int $suffixLength
     *
     * @return self<T>
     */
    public function withoutSuffix(int $suffixLength): self
    {
        if ($suffixLength > $this->length) {
            throw new OutOfBoundsException(ExceptionUtil::buildMessage('suffixLength is larger than length', compact('suffixLength', 'this')));
        }
        return new self($this->base, $this->offset, $this->length - $suffixLength); // @phpstan-ignore argument.type
    }

    /**
     * @return T
     */
    public function getLastValue(): mixed
    {
        if ($this->length === 0) {
            throw new OutOfBoundsException(ExceptionUtil::buildMessage(' is empty', compact('this')));
        }
        return $this->base[$this->offset + $this->length - 1];
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(array_slice($this->base, $this->offset, $this->length));
    }
}
