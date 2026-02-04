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

/** @noinspection PhpUnitMisorderedAssertEqualsArgumentsInspection */

declare(strict_types=1);

namespace ElasticOTelTests\UnitTests\UtilTests;

use Brick\Math\BigInteger;
use ElasticOTelTests\ComponentTests\Util\OtlpData\FlagsBase;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\TestCaseBase;
use Override;

final class FlagsBaseTest extends TestCaseBase
{
    public function testToString(): void
    {
        $impl = function (int|BigInteger $val, string $expectedToStringResult): void {
            $flagsObj = new class (is_int($val) ? strval($val) : $val->toBase(10)) extends FlagsBase {
                #[Override]
                protected function maskToName(): array
                {
                    return [
                        1 => 'INDEX_0_FLAG',
                        2 => 'INDEX_1_FLAG',
                        4 => 'INDEX_2_FLAG',
                        32 => 'INDEX_5_FLAG',
                    ];
                }
            };

            self::assertSame($expectedToStringResult, $flagsObj->__toString());
        };

        $impl(0, '0');
        $impl(1, '0b1 (INDEX_0_FLAG)');
        $impl(2, '0b10 (INDEX_1_FLAG)');
        $impl(3, '0b11 (INDEX_0_FLAG | INDEX_1_FLAG)');
        $impl(4, '0b100 (INDEX_2_FLAG)');
        $impl(5, '0b101 (INDEX_0_FLAG | INDEX_2_FLAG)');
        $impl(6, '0b110 (INDEX_1_FLAG | INDEX_2_FLAG)');
        $impl(7, '0b111 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_2_FLAG)');
        $impl(8, '0b1000 (unnamed bits: 0b1000)');
        $impl(9, '0b1001 (INDEX_0_FLAG | unnamed bits: 0b1000)');
        $impl(10, '0b1010 (INDEX_1_FLAG | unnamed bits: 0b1000)');
        $impl(11, '0b1011 (INDEX_0_FLAG | INDEX_1_FLAG | unnamed bits: 0b1000)');
        $impl(12, '0b1100 (INDEX_2_FLAG | unnamed bits: 0b1000)');
        $impl(13, '0b1101 (INDEX_0_FLAG | INDEX_2_FLAG | unnamed bits: 0b1000)');
        $impl(14, '0b1110 (INDEX_1_FLAG | INDEX_2_FLAG | unnamed bits: 0b1000)');
        $impl(15, '0b1111 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_2_FLAG | unnamed bits: 0b1000)');
        $impl(16, '0b10000 (unnamed bits: 0b10000)');
        $impl(31, '0b11111 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_2_FLAG | unnamed bits: 0b11000)');
        $impl(32, '0b100000 (INDEX_5_FLAG)');
        $impl(33, '0b100001 (INDEX_0_FLAG | INDEX_5_FLAG)');
        $impl(34, '0b100010 (INDEX_1_FLAG | INDEX_5_FLAG)');
        $impl(35, '0b100011 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_5_FLAG)');
        $impl(AssertEx::isInt(bindec('1100011')), '0b1100011 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_5_FLAG | unnamed bits: 0b1000000)');
        $impl(AssertEx::isInt(bindec('10100011')), '0b10100011 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_5_FLAG | unnamed bits: 0b10000000)');

        $thousandZeros = str_repeat('0', 1000);
        $impl(BigInteger::fromBase("1$thousandZeros", 2), "0b1$thousandZeros (unnamed bits: 0b1$thousandZeros)");
        $impl(BigInteger::fromBase("1{$thousandZeros}1", 2), "0b1{$thousandZeros}1 (INDEX_0_FLAG | unnamed bits: 0b1{$thousandZeros}0)");
        $impl(BigInteger::fromBase("1{$thousandZeros}10", 2), "0b1{$thousandZeros}10 (INDEX_1_FLAG | unnamed bits: 0b1{$thousandZeros}00)");
        $impl(BigInteger::fromBase("1{$thousandZeros}11", 2), "0b1{$thousandZeros}11 (INDEX_0_FLAG | INDEX_1_FLAG | unnamed bits: 0b1{$thousandZeros}00)");
        $impl(BigInteger::fromBase("1{$thousandZeros}111", 2), "0b1{$thousandZeros}111 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_2_FLAG | unnamed bits: 0b1{$thousandZeros}000)");
        $impl(BigInteger::fromBase("1{$thousandZeros}111111", 2), "0b1{$thousandZeros}111111 (INDEX_0_FLAG | INDEX_1_FLAG | INDEX_2_FLAG | INDEX_5_FLAG | unnamed bits: 0b1{$thousandZeros}011000)");
    }

    public function testIsOn(): void
    {
        $impl = function (string $valBinary, string $maskBinary, bool $expectedIsOn): void {
            $mask = strlen($maskBinary) <= 32 ? AssertEx::isInt(bindec($maskBinary)) : (BigInteger::fromBase($maskBinary, base: 2));
            self::assertSame($expectedIsOn, (new FlagsBase(BigInteger::fromBase($valBinary, 2)->toBase(10)))->isOn($mask));
        };

        $impl('0', '0', false);
        $impl('1', '0', false);
        $impl('0', '1', false);
        $impl('1', '1', true);

        $impl('10', '01', false);
        $impl('10', '10', true);
        $impl('10', '11', false);

        $impl('11', '01', true);
        $impl('11', '10', true);
        $impl('11', '11', true);

        $thousandZeros = str_repeat('0', 1000);
        $impl("$thousandZeros", '0', false);
        $impl("$thousandZeros", '1', false);
        $impl("{$thousandZeros}1", '0', false);
        $impl("{$thousandZeros}1", '1', true);
        $impl("1$thousandZeros", '0', false);
        $impl("1$thousandZeros", '1', false);
        $impl("1$thousandZeros", '10', false);
        $impl("1$thousandZeros", "1$thousandZeros", true);
        $impl("10$thousandZeros", "1$thousandZeros", false);
        $impl("11$thousandZeros", "1$thousandZeros", true);
        $impl("11$thousandZeros", "10$thousandZeros", true);
        $impl("11$thousandZeros", "11$thousandZeros", true);
    }
}
