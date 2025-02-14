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

use Elastic\OTel\Util\StaticClassTrait;
use InvalidArgumentException;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NumericUtilForTests
{
    use StaticClassTrait;

    public static function compare(int|float $lhs, int|float $rhs): int
    {
        return ($lhs < $rhs) ? -1 : (($lhs == $rhs) ? 0 : 1);
    }

    /**
     * @template TNumber of int|float
     *
     * @param array<TNumber> $lhs
     * @param array<TNumber> $rhs
     *
     * @return int
     */
    public static function compareSequences(array $lhs, array $rhs): int
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        if (count($lhs) !== count($rhs)) {
            throw new InvalidArgumentException(ExceptionUtil::buildMessage('Sequences sizes do not match', compact('lhs', 'rhs')));
        }

        foreach (IterableUtil::zipWithIndex($lhs, $rhs) as [$index, $lhsElement, $rhsElement]) {
            /** @var TNumber $lhsElement */
            /** @var TNumber $rhsElement */
            $dbgCtx->add(compact('index', 'lhsElement', 'rhsElement'));
            if (($compareRetVal = self::compare($lhsElement, $rhsElement)) !== 0) {
                return $compareRetVal;
            }
        }
        return 0;
    }
}
