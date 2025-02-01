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

use ElasticOTelTests\Util\Config\EnumOptionParser;
use ElasticOTelTests\Util\TestCaseBase;

class EnumOptionsParsingTest extends TestCaseBase
{
    /**
     * @return list<array{EnumOptionParser<mixed>, list<OptionTestValidValue<mixed>>}>
     */
    public static function dataProviderForTestEnumWithSomeEntriesArePrefixOfOtherOnes(): array
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $testArgsTuples = [
            [
                EnumOptionParser::useEnumCasesNames(EnumOptionsParsingTestDummyEnum::class, isCaseSensitive: true, isUnambiguousPrefixAllowed: true),
                [
                    new OptionTestValidValue(" anotherEnumEntry\t\n", EnumOptionsParsingTestDummyEnum::anotherEnumEntry),
                    new OptionTestValidValue("anotherEnumEnt  \n ", EnumOptionsParsingTestDummyEnum::anotherEnumEntry),
                    new OptionTestValidValue("another  \n ", EnumOptionsParsingTestDummyEnum::anotherEnumEntry),
                    new OptionTestValidValue('a', EnumOptionsParsingTestDummyEnum::anotherEnumEntry),
                    new OptionTestValidValue(' enumEntry', EnumOptionsParsingTestDummyEnum::enumEntry),
                    new OptionTestValidValue("\t  enumEntryWithSuffix\n ", EnumOptionsParsingTestDummyEnum::enumEntryWithSuffix),
                    new OptionTestValidValue('enumEntryWithSuffix2', EnumOptionsParsingTestDummyEnum::enumEntryWithSuffix2),
                ]
            ],
            [
                EnumOptionParser::useEnumCasesValues(EnumOptionsParsingTestDummyBackedEnum::class, isCaseSensitive: true, isUnambiguousPrefixAllowed: true),
                [
                    new OptionTestValidValue(" anotherEnumEntry_value\t\n", EnumOptionsParsingTestDummyBackedEnum::anotherEnumEntry),
                    new OptionTestValidValue("anotherEnumEnt  \n ", EnumOptionsParsingTestDummyBackedEnum::anotherEnumEntry),
                    new OptionTestValidValue("another  \n ", EnumOptionsParsingTestDummyBackedEnum::anotherEnumEntry),
                    new OptionTestValidValue('a', EnumOptionsParsingTestDummyBackedEnum::anotherEnumEntry),
                    new OptionTestValidValue(' enumEntry_value', EnumOptionsParsingTestDummyBackedEnum::enumEntry),
                    new OptionTestValidValue("\t  enumEntryWithSuffix_value\n ", EnumOptionsParsingTestDummyBackedEnum::enumEntryWithSuffix),
                    new OptionTestValidValue('enumEntryWithSuffix2_value', EnumOptionsParsingTestDummyBackedEnum::enumEntryWithSuffix2),
                ]
            ],
            [
                new EnumOptionParser(
                    dbgDesc: '<enum defined in ' . __METHOD__ . '>',
                    nameValuePairs: [
                        ['enumEntry', 'enumEntry_value'],
                        ['enumEntryWithSuffix', 'enumEntryWithSuffix_value'],
                        ['enumEntryWithSuffix2', 'enumEntryWithSuffix2_value'],
                        ['anotherEnumEntry', 'anotherEnumEntry_value'],
                    ],
                    isCaseSensitive:            true,
                    isUnambiguousPrefixAllowed: true
                ),
                [
                    new OptionTestValidValue(" anotherEnumEntry\t\n", 'anotherEnumEntry_value'),
                    new OptionTestValidValue("anotherEnumEnt  \n ", 'anotherEnumEntry_value'),
                    new OptionTestValidValue("another  \n ", 'anotherEnumEntry_value'),
                    new OptionTestValidValue('a', 'anotherEnumEntry_value'),
                    new OptionTestValidValue(' enumEntry', 'enumEntry_value'),
                    new OptionTestValidValue("\t  enumEntryWithSuffix\n ", 'enumEntryWithSuffix_value'),
                    new OptionTestValidValue('enumEntryWithSuffix2', 'enumEntryWithSuffix2_value'),
                ],
            ],
        ];

        return $testArgsTuples; // @phpstan-ignore return.type
    }

    /**
     * @template T
     *
     * @dataProvider dataProviderForTestEnumWithSomeEntriesArePrefixOfOtherOnes
     *
     * @param EnumOptionParser<T>           $optionParser
     * @param list<OptionTestValidValue<T>> $additionalValidValues
     */
    public function testEnumWithSomeEntriesArePrefixOfOtherOnes(EnumOptionParser $optionParser, array $additionalValidValues): void
    {
        /** @noinspection SpellCheckingInspection */
        /** @var list<string> $additionalInvalidRawValues */
        static $additionalInvalidRawValues = [
            'e',
            'enum',
            'enumEnt',
            'enumEntr',
            'enumEntryWithSuffi',
            'enumEntryWithSuffix2_',
            'ENUMENTRY',
            'enumEntryWithSUFFIX',
            'ENUMEntryWithSuffix2',
            'anotherenumentry',
            'Another',
            'A',
        ];

        $testValuesGenerator = new EnumOptionTestValuesGenerator($optionParser, $additionalValidValues, $additionalInvalidRawValues);

        VariousOptionsParsingTest::parseValidValueTestImpl($testValuesGenerator, $optionParser);
        VariousOptionsParsingTest::parseInvalidValueTestImpl($testValuesGenerator, $optionParser);
    }
}
