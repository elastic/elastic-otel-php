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

use ElasticOTelTests\Util\DebugContext;
use Override;
use PHPUnit\Framework\Assert;

trait ExpectationsTrait
{
    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        $this->assertObjectMatchesTraitImpl($this, $actual);
    }

    protected static function assertValueMatches(mixed $expected, mixed $actual): void
    {
        if (is_object($expected)) {
            self::assertObjectMatches($expected, $actual);
            return;
        }

        Assert::assertSame($expected, $actual);
    }

    protected static function assertObjectMatches(object $expected, mixed $actual): void
    {
        if ($expected instanceof ExpectationsInterface) {
            $expected->assertMatchesMixed($actual);
            return;
        }

        static::assertObjectMatchesTraitImpl($expected, $actual);
    }

    protected static function assertObjectMatchesTraitImpl(object $expected, mixed $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        Assert::assertIsObject($actual);

        /** @var string $propName */
        foreach ($expected as $propName => $expectationsPropValue) { // @phpstan-ignore foreach.nonIterable
            $dbgCtx->add(compact('propName', 'expectationsPropValue'));
            Assert::assertTrue(property_exists($actual, $propName));
            static::assertValueMatches($expectationsPropValue, $actual->$propName);
        }
    }
}
