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

use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\SemConv\TraceAttributes;
use Override;

/**
 * @phpstan-import-type AttributeValue from SpanAttributes
 */
final class SpanAttributesExpectations implements ExpectationsInterface
{
    use ExpectationsTrait;

    /** @var ArrayExpectations<string, AttributeValue> */
    private readonly ArrayExpectations $arrayExpectations;

    /**
     * @param array<string, AttributeValue> $attributes
     */
    public function __construct(array $attributes, bool $allowOtherKeysInActual = true)
    {
        $this->arrayExpectations = new class ($attributes, $allowOtherKeysInActual) extends ArrayExpectations {
            #[Override]
            protected function assertValueMatches(string|int $key, mixed $expectedValue, mixed $actualValue): void
            {
                if ($key === TraceAttributes::URL_SCHEME) {
                    TestCaseBase::assertEqualsIgnoringCase($expectedValue, $actualValue);
                } else {
                    TestCaseBase::assertSame($expectedValue, $actualValue);
                }
            }
        };
    }

    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        TestCaseBase::assertInstanceOf(SpanAttributes::class, $actual);
        $this->assertMatches($actual);
    }

    public function assertMatches(SpanAttributes $actual): void
    {
        $this->arrayExpectations->assertMatches($actual);
    }
}
