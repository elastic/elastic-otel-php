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

namespace ElasticOTelTests\ComponentTests\Util\OtlpData;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Override;
use Stringable;

/**
 * @phpstan-type BitMaskToName array<int, string>
 */
class FlagsBase implements LoggableInterface, Stringable
{
    private readonly BigInteger $value;

    public function __construct(int|string $decimalVal)
    {
        $this->value = is_int($decimalVal) ? BigInteger::of($decimalVal) : BigInteger::fromBase($decimalVal, 10);
    }

    /**
     * @return BitMaskToName
     */
    protected function maskToName(): array
    {
        return [];
    }

    private static function isZero(BigNumber|int $val): bool
    {
        return is_int($val) ? ($val === 0) : $val->isZero();
    }

    public function isOn(BigInteger|int $mask): bool
    {
        if (self::isZero($mask)) {
            return false;
        }
        return $this->value->and($mask)->isEqualTo($mask);
    }

    private static function toBinaryWithPrefix(BigInteger $bigInt): string
    {
        return '0b' . $bigInt->toBase(2);
    }

    public function __toString(): string
    {
        if ($this->value->isZero()) {
            return '0';
        }

        $foundNames = [];
        $bitsForFoundNames = 0;
        foreach ($this->maskToName() as $mask => $name) {
            if ($this->isOn($mask)) {
                $foundNames[] = $name;
                $bitsForFoundNames |= $mask;
            }
        }

        if (!$this->value->isEqualTo($bitsForFoundNames)) {
            $remainingBits = $this->value->and(~$bitsForFoundNames);
            $foundNames[] = 'unnamed bits: ' . self::toBinaryWithPrefix($remainingBits);
        }

        return self::toBinaryWithPrefix($this->value) . ' (' . implode(' | ', $foundNames) . ')';
    }

    #[Override]
    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->__toString());
    }
}
