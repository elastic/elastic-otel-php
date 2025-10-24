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

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BoolUtil
{
    use StaticClassTrait;

    public const INT_FOR_FALSE = 0;
    public const INT_FOR_TRUE = 1;
    public const ALL_VALUES = [true, false];
    public const ALL_NULLABLE_VALUES = [null, true, false];

    public static function ifThen(bool $ifCond, bool $thenCond): bool
    {
        return $ifCond ? $thenCond : true;
    }

    public static function toString(bool $val): string
    {
        return $val ? 'true' : 'false';
    }

    public static function toInt(bool $val): int
    {
        return $val ? self::INT_FOR_TRUE : self::INT_FOR_FALSE;
    }

    public static function fromString(string $stringVal): bool
    {
        /** @var list<string> $trueStringValues */
        static $trueStringValues = ['true', 'yes', 'on', '1'];

        foreach ($trueStringValues as $trueStringValue) {
            if (strcasecmp($stringVal, $trueStringValue) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<bool>
     */
    public static function allValuesStartingFrom(bool $startingValue): array
    {
        return [$startingValue, !$startingValue];
    }
}
