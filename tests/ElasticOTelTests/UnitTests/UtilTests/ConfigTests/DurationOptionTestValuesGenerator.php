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

namespace ElasticOTelTests\UnitTests\UtilTests\ConfigTests;

use ElasticOTelTests\Util\Config\DurationOptionParser;
use ElasticOTelTests\Util\Config\FloatOptionParser;
use ElasticOTelTests\Util\Duration;
use ElasticOTelTests\Util\DurationUnit;

/**
 * @implements OptionTestValuesGeneratorInterface<Duration>
 */
final class DurationOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    private DurationOptionParser $optionParser;
    private FloatOptionTestValuesGenerator $auxFloatValuesGenerator;

    public function __construct(DurationOptionParser $optionParser)
    {
        $this->optionParser = $optionParser;
        $this->auxFloatValuesGenerator = self::buildAuxFloatValuesGenerator($optionParser);
    }

    /**
     * @return iterable<OptionTestValidValue<Duration>>
     */
    private function createIfValidValue(string $valueAsString, DurationUnit $units, string $unitsSuffix): iterable
    {
        $value = new Duration(floatval($valueAsString), $units);
        if ($this->auxFloatValuesGenerator->isInValidRange($value->toMilliseconds())) {
            yield new OptionTestValidValue($valueAsString . $unitsSuffix, $value);
        }
    }

    /**
     * @return iterable<OptionTestValidValue<Duration>>
     */
    public function validValues(): iterable
    {
        $noUnits = function (float|int $valueWithoutUnits): float {
            return Duration::valueToMilliseconds(floatval($valueWithoutUnits), $this->optionParser->defaultUnits);
        };

        /**
         * We are forced to use list-array of pairs instead of regular associative array
         * because in an associative array if the key is numeric string it's automatically converted to int
         * (see https://www.php.net/manual/en/language.types.array.php)
         *
         * @var array<array{string, float|int}> $predefinedValidValues
         */
        $predefinedValidValues = [
            ['0', 0],
            [' 0 ms', 0],
            ["\t 0 s ", 0],
            ['0m', 0],
            ['1', $noUnits(1)],
            ['0.01', $noUnits(0.01)],
            ['97.5', $noUnits(97.5)],
            ['1ms', 1],
            [" \n 97 \t ms ", 97],
            ['1s', 1000],
            ['1m', 60 * 1000],
            ['0.0', 0],
            ['0.0ms', 0],
            ['0.0s', 0],
            ['0.0m', 0],
            ['1.5ms', 1.5],
            ['1.5s', 1.5 * 1000],
            ['1.5m', 1.5 * 60 * 1000],
            ['-12ms', -12],
            ['-12.5ms', -12.5],
            ['-45s', -45 * 1000],
            ['-45.1s', -45.1 * 1000],
            ['-78m', -78 * 60 * 1000],
            ['-78.2m', -78.2 * 60 * 1000],
        ];
        foreach ($predefinedValidValues as $rawAndParsedValuesPair) {
            if ($this->auxFloatValuesGenerator->isInValidRange($rawAndParsedValuesPair[1])) {
                yield new OptionTestValidValue($rawAndParsedValuesPair[0], new Duration(floatval($rawAndParsedValuesPair[1]), DurationUnit::ms));
            }
        }

        /** @var OptionTestValidValue<float> $validNoUnitsValue */
        foreach ($this->auxFloatValuesGenerator->validValues() as $validNoUnitsValue) {
            foreach (DurationUnit::cases() as $unit) {
                $unitsSuffixes = [$unit->name, ' ' . $unit->name];
                if ($unit === $this->optionParser->defaultUnits) {
                    $unitsSuffixes[] = '';
                }
                foreach ($unitsSuffixes as $unitsSuffix) {
                    $valueInUnits = self::convertFromMilliseconds($validNoUnitsValue->parsedValue, $unit);

                    // For float keep only 3 digits after the floating point
                    // for tolerance to error in reverse conversion
                    $roundedValueInUnits = round($valueInUnits, 3);

                    yield from $this->createIfValidValue(strval($roundedValueInUnits), $unit, $unitsSuffix);

                    foreach ([ceil($roundedValueInUnits), floor($roundedValueInUnits)] as $intValueInUnits) {
                        if (FloatOptionTestValuesGenerator::isInIntRange($intValueInUnits)) {
                            $intValueInUnitsAsString = strval(intval($intValueInUnits));
                            yield from $this->createIfValidValue($intValueInUnitsAsString, $unit, $unitsSuffix);

                            yield from $this->createIfValidValue(strval($intValueInUnits), $unit, $unitsSuffix);
                        }
                    }
                }
            }
        }
    }

    public function invalidRawValues(): iterable
    {
        yield from [
            '',
            ' ',
            '\t',
            '\r\n',
            'a',
            'abc',
            '123abc',
            'abc123',
            'a_123_b',
            '1a',
            '1sm',
            '1m2',
            '1s2',
            '1ms2',
            '3a2m',
            'a32m',
            '3a2s',
            'a32s',
            '3a2ms',
            'a32ms',
        ];

        foreach ($this->auxFloatValuesGenerator->invalidRawValues() as $invalidRawValue) {
            if (!FloatOptionParser::isValidFormat($invalidRawValue)) {
                yield $invalidRawValue;
                continue;
            }

            $invalidValueInMilliseconds = floatval($invalidRawValue);
            if (!$this->auxFloatValuesGenerator->isInValidRange($invalidValueInMilliseconds)) {
                foreach (DurationUnit::cases() as $unit) {
                    $valueInUnits = self::convertFromMilliseconds($invalidValueInMilliseconds, $unit);
                    yield $valueInUnits . $unit->name;
                    if ($this->optionParser->defaultUnits === $unit) {
                        yield strval($valueInUnits);
                    }
                }
            }
        }

        /** @var OptionTestValidValue<float> $validValue */
        foreach ($this->validValues() as $validValue) {
            foreach (['a', 'z'] as $invalidDurationUnitsSuffix) {
                yield $validValue->rawValue . $invalidDurationUnitsSuffix;
            }
        }
    }

    public static function convertFromMilliseconds(float $valueInMilliseconds, DurationUnit $dstUnit): float
    {
        return match ($dstUnit) {
            DurationUnit::ms => $valueInMilliseconds,
            DurationUnit::s => $valueInMilliseconds / 1000,
            DurationUnit::m => $valueInMilliseconds / (60 * 1000),
        };
    }

    private static function buildAuxFloatValuesGenerator(DurationOptionParser $optionParser): FloatOptionTestValuesGenerator
    {
        $floatOptionParser = new FloatOptionParser($optionParser->minValidValue?->value, $optionParser->maxValidValue?->value);

        return new class ($floatOptionParser) extends FloatOptionTestValuesGenerator {
            /**
             * @return iterable<float>
             */
            protected function autoGeneratedInterestingValuesToDiff(): iterable
            {
                foreach (parent::autoGeneratedInterestingValuesToDiff() as $interestingValuesToDiff) {
                    foreach (DurationUnit::cases() as $unit) {
                        yield DurationOptionTestValuesGenerator::convertFromMilliseconds($interestingValuesToDiff, $unit);
                    }
                }
            }
        };
    }
}
