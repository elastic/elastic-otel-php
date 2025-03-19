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

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Optional;
use Override;
use PHPUnit\Framework\Assert;

final class NullableStringExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /**
     * @param Optional<?string> $expectedValue
     * @param bool              $isExpectedValueRegex
     */
    private function __construct(
        public readonly Optional $expectedValue,
        public readonly bool $isExpectedValueRegex = false,
    ) {
    }

    public static function regex(string $expectedValueRegex): self
    {
        return new self(Optional::value($expectedValueRegex), isExpectedValueRegex: true); // @phpstan-ignore argument.type
    }

    public static function literal(?string $expectedValue): self
    {
        return new self(Optional::value($expectedValue)); // @phpstan-ignore argument.type
    }

    public static function matchAny(): self
    {
        /** @var ?self $cached */
        static $cached = null;
        return $cached ??= new self(Optional::none()); // @phpstan-ignore argument.type
    }

    public function assertMatches(?string $actual): void
    {
        if (!$this->expectedValue->isValueSet()) {
            return;
        }

        if ($this->isExpectedValueRegex) {
            Assert::assertMatchesRegularExpression(AssertEx::notNull($this->expectedValue->getValue()), AssertEx::notNull($actual));
        } else {
            Assert::assertSame($this->expectedValue->getValue(), $actual);
        }
    }

    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        $this->assertMatches(AssertEx::isNullableString($actual));
    }
}
