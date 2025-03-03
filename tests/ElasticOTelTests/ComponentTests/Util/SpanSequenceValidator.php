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

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\TimeUtil;
use PHPUnit\Framework\Assert;

final class SpanSequenceValidator
{
    use StaticClassTrait;

    /**
     * @param Span[] $spans
     *
     * @return Span[]
     */
    private static function sortByStartTime(array $spans): array
    {
        usort(
            $spans,
            function (Span $span_1, Span $span_2): int {
                return TimeUtil::compareTimestamps($span_1->startTimeUnixNano, $span_2->startTimeUnixNano);
            }
        );
        return $spans;
    }

    /**
     * @param SpanExpectations[] $expected
     * @param Span[]             $actual
     */
    public static function assertSequenceAsExpected(array $expected, array $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        AssertEx::sameCount($expected, $actual);
        $actualSortedByStartTime = self::sortByStartTime($actual);
        $index = 0;
        /** @var ?Span $prevActualSpan */
        $prevActualSpan = null;
        foreach (IterableUtil::zip($expected, $actualSortedByStartTime) as [$expectedSpan, $actualSpan]) {
            /** @var SpanExpectations $expectedSpan */
            /** @var Span $actualSpan */
            $dbgCtx->add(compact('index', 'expectedSpan', 'actualSpan'));
            if ($index != 0) {
                Assert::assertNotNull($prevActualSpan);
            }
            $expectedSpan->assertMatches($actualSpan);
            $prevActualSpan = $actualSpan;
            ++$index;
        }
    }
}
