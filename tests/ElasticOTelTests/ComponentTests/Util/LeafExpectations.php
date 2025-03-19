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

use Override;

/**
 * @template T
 */
final class LeafExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /**
     * @param T $expectedValue
     */
    private function __construct(
        public readonly mixed $expectedValue = null,
        public readonly bool $shouldMatchAny = false,
    ) {
    }

    /**
     * @param T $expectedValue
     *
     * @return self<T>
     */
    public static function expectedValue(mixed $expectedValue): self
    {
        return new self($expectedValue);
    }

    /**
     * @return self<mixed>
     */
    public static function matchAny(): self
    {
        /** @var ?self<mixed> $cached */
        static $cached = null;
        return $cached ??= new self(shouldMatchAny: true);
    }

    /**
     * @param T $actual
     */
    public function assertMatches(mixed $actual): void
    {
        $this->assertMatchesMixed($actual);
    }

    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        if ($this->shouldMatchAny) {
            return;
        }

        $this->assertValueMatches($this->expectedValue, $actual);
    }
}
