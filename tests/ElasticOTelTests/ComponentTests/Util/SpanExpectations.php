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

use ElasticOTelTests\Util\DbgUtil;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\SemConv\TraceAttributes;
use UnitEnum;

/**
 * @phpstan-import-type AttributeValue from SpanAttributes
 */
final class SpanExpectations
{
    /**
     * @param ?string                        $name
     * @param ?SpanKind                      $kind
     * @param ?array<string, AttributeValue> $attributes
     */
    public function __construct(
        public ?string $name = null,
        public ?SpanKind $kind = null,
        public ?array $attributes = null,
    ) {
    }

    public function assertMatches(Span $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            $dbgCtx->add(compact('this'));
            $dbgCtx->pushSubScope();
            foreach (get_object_vars($this) as $propName => $expectationsPropValue) {
                $dbgCtx->clearCurrentSubScope(compact('propName', 'expectationsPropValue'));
                TestCaseBase::assertTrue(property_exists($actual, $propName));
                self::assertPropertyValueMatches($propName, $expectationsPropValue, $actual->$propName);
            }
            $dbgCtx->popSubScope();
        } finally {
            $dbgCtx->pop();
        }
    }

    private static function assertPropertyValueMatches(string $propName, mixed $expectationsPropValue, mixed $actualPropValue): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            if ($expectationsPropValue === null) {
                return;
            }

            if (is_scalar($expectationsPropValue)) {
                TestCaseBase::assertSame($expectationsPropValue, $actualPropValue);
                return;
            }

            if ($expectationsPropValue instanceof UnitEnum) {
                TestCaseBase::assertSame($expectationsPropValue, $actualPropValue);
                return;
            }

            if (is_array($expectationsPropValue)) {
                if ($propName === 'attributes') {
                    TestCaseBase::assertInstanceOf(SpanAttributes::class, $actualPropValue);
                    self::assertAttributesMatch($expectationsPropValue, $actualPropValue);
                } else {
                    TestCaseBase::assertIsArray($actualPropValue);
                    $dbgCtx->pushSubScope();
                    foreach ($expectationsPropValue as $expectedKey => $expectedValue) {
                        TestCaseBase::assertArrayHasKeyWithValue($expectedKey, $expectedValue, $actualPropValue);
                    }
                    $dbgCtx->popSubScope();
                }
                return;
            }

            TestCaseBase::fail('Unexpected expectationsPropValue type' . DbgUtil::getType($expectationsPropValue));
        } finally {
            $dbgCtx->pop();
        }
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    private static function assertAttributesMatch(array $expected, SpanAttributes $actual): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            $dbgCtx->pushSubScope();
            foreach ($expected as $expectedKey => $expectedValue) {
                $dbgCtx->clearCurrentSubScope(compact('expectedKey', 'expectedValue'));
                TestCaseBase::assertTrue($actual->get($expectedKey, /* out */ $actualValue));
                $dbgCtx->add(compact('actualValue'));
                if ($expectedKey === TraceAttributes::URL_SCHEME) {
                    TestCaseBase::assertEqualsIgnoringCase($expectedValue, $actualValue);
                } else {
                    TestCaseBase::assertSame($expectedValue, $actualValue);
                }
            }
            $dbgCtx->popSubScope();
        } finally {
            $dbgCtx->pop();
        }
    }
}
