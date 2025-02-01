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

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\BoolOptionParser;
use ElasticOTelTests\Util\Config\ConfigSnapshotForTests;
use ElasticOTelTests\Util\Config\CustomOptionParser;
use ElasticOTelTests\Util\Config\DurationOptionMetadata;
use ElasticOTelTests\Util\Config\DurationOptionParser;
use ElasticOTelTests\Util\Config\EnumOptionParser;
use ElasticOTelTests\Util\Config\FloatOptionMetadata;
use ElasticOTelTests\Util\Config\FloatOptionParser;
use ElasticOTelTests\Util\Config\IntOptionParser;
use ElasticOTelTests\Util\Config\OptionMetadata;
use ElasticOTelTests\Util\Config\OptionParser;
use ElasticOTelTests\Util\Config\OptionsForTestsMetadata;
use ElasticOTelTests\Util\Config\ParseException;
use ElasticOTelTests\Util\Config\Parser;
use ElasticOTelTests\Util\Config\StringOptionParser;
use ElasticOTelTests\Util\Config\WildcardListOptionParser;
use ElasticOTelTests\Util\DbgUtil;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\Duration;
use ElasticOTelTests\Util\DurationUnit;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\RandomUtil;
use ElasticOTelTests\Util\RangeUtil;
use ElasticOTelTests\Util\TestCaseBase;

class VariousOptionsParsingTest extends TestCaseBase
{
    /**
     * @param OptionMetadata<mixed> $optMeta
     *
     * @return OptionTestValuesGeneratorInterface<mixed>
     */
    private static function selectTestValuesGenerator(OptionMetadata $optMeta): OptionTestValuesGeneratorInterface
    {
        $optionParser = $optMeta->parser();

        if ($optionParser instanceof BoolOptionParser) {
            return new EnumOptionTestValuesGenerator($optionParser, additionalValidValues: [new OptionTestValidValue('', false)]); /** @phpstan-ignore return.type */
        }
        if ($optionParser instanceof DurationOptionParser) {
            return new DurationOptionTestValuesGenerator($optionParser); /** @phpstan-ignore return.type */
        }
        if ($optionParser instanceof EnumOptionParser) {
            return new EnumOptionTestValuesGenerator($optionParser);
        }
        if ($optionParser instanceof FloatOptionParser) {
            return new FloatOptionTestValuesGenerator($optionParser); /** @phpstan-ignore return.type */
        }
        if ($optionParser instanceof IntOptionParser) {
            return new IntOptionTestValuesGenerator($optionParser); /** @phpstan-ignore return.type */
        }
        if ($optionParser instanceof StringOptionParser) {
            return StringOptionTestValuesGenerator::singletonInstance(); /** @phpstan-ignore return.type */
        }
        if ($optionParser instanceof WildcardListOptionParser) {
            return WildcardListOptionTestValuesGenerator::singletonInstance(); /** @phpstan-ignore return.type */
        }

        self::fail('Unknown option metadata type: ' . DbgUtil::getType($optMeta));
    }

    /**
     * @return array<string, OptionMetadata<mixed>>
     */
    private static function additionalOptionMetas(): array
    {
        $result = [];

        $result['Duration s units'] = new DurationOptionMetadata(
            new Duration(10, DurationUnit::ms) /* minValidValue */,
            new Duration(20, DurationUnit::ms) /* maxValidValue */,
            DurationUnit::s /* <- defaultUnits */,
            new Duration(15, DurationUnit::ms) /* <- defaultValue */
        );

        $result['Duration m units'] = new DurationOptionMetadata(
            null /* minValidValue */,
            null /* maxValidValue */,
            DurationUnit::m /* <- defaultUnits */,
            new Duration(123, DurationUnit::m) /* <- defaultValue */
        );

        $result['Float without constrains'] = new FloatOptionMetadata(
            null /* minValidValue */,
            null /* maxValidValue */,
            123.321 /* defaultValue */
        );

        $result['Float only with min constrain'] = new FloatOptionMetadata(
            -1.0 /* minValidValue */,
            null /* maxValidValue */,
            456.789 /* defaultValue */
        );

        $result['Float only with max constrain'] = new FloatOptionMetadata(
            null /* minValidValue */,
            1.0 /* maxValidValue */,
            -987.654 /* defaultValue */
        );

        return $result; // @phpstan-ignore-line
    }

    /**
     * @return array<string|null, array<string, OptionMetadata<mixed>>>
     */
    private static function snapshotClassToOptionsMeta(): array
    {
        return [
            ConfigSnapshotForTests::class => OptionsForTestsMetadata::get(),
            null                          => self::additionalOptionMetas(),
        ];
    }

    /**
     * @return iterable<array{string, OptionMetadata<mixed>}>
     */
    public static function allOptionsMetadataProvider(): iterable
    {
        foreach (self::snapshotClassToOptionsMeta() as $optionsMeta) {
            foreach ($optionsMeta as $optMeta) {
                if (!$optMeta->parser() instanceof CustomOptionParser) {
                    yield [LoggableToString::convert($optMeta), $optMeta];
                }
            }
        }
    }

    /**
     * @return iterable<array{string, OptionMetadata<mixed>}>
     */
    public static function allOptionsMetadataWithPossibleInvalidRawValuesProvider(): iterable
    {
        foreach (self::allOptionsMetadataProvider() as $optMetaDescAndDataPair) {
            /** @var OptionMetadata<mixed> $optMeta */
            $optMeta = $optMetaDescAndDataPair[1];
            if (!IterableUtil::isEmpty(self::selectTestValuesGenerator($optMeta)->invalidRawValues())) {
                yield $optMetaDescAndDataPair;
            }
        }
    }

    public function testIntOptionParserIsValidFormat(): void
    {
        self::assertTrue(IntOptionParser::isValidFormat('0'));
        self::assertFalse(IntOptionParser::isValidFormat('0.0'));
        self::assertTrue(IntOptionParser::isValidFormat('+0'));
        self::assertFalse(IntOptionParser::isValidFormat('+0.0'));
        self::assertTrue(IntOptionParser::isValidFormat('-0'));
        self::assertFalse(IntOptionParser::isValidFormat('-0.0'));

        self::assertTrue(IntOptionParser::isValidFormat('1'));
        self::assertFalse(IntOptionParser::isValidFormat('1.0'));
        self::assertTrue(IntOptionParser::isValidFormat('+1'));
        self::assertFalse(IntOptionParser::isValidFormat('+1.0'));
        self::assertTrue(IntOptionParser::isValidFormat('-1'));
        self::assertFalse(IntOptionParser::isValidFormat('-1.0'));
    }

    /**
     * @param OptionTestValuesGeneratorInterface<mixed> $testValuesGenerator
     * @param OptionParser<mixed>                       $optParser
     */
    public static function parseInvalidValueTestImpl(OptionTestValuesGeneratorInterface $testValuesGenerator, OptionParser $optParser): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        try {
            $invalidRawValues = $testValuesGenerator->invalidRawValues();
            if (IterableUtil::isEmpty($invalidRawValues)) {
                self::dummyAssert();
                return;
            }
            $dbgCtx->add(['invalidRawValues' => $invalidRawValues]);

            $dbgCtx->pushSubScope();
            foreach ($invalidRawValues as $invalidRawValue) {
                $invalidRawValue = self::genOptionalWhitespace() . $invalidRawValue . self::genOptionalWhitespace();
                $dbgCtx->add(['invalidRawValue' => $invalidRawValue, 'strlen($invalidRawValue)' => strlen($invalidRawValue)]);
                if (!TextUtil::isEmptyString($invalidRawValue)) {
                    $dbgCtx->add(['ord($invalidRawValue[0])' => ord($invalidRawValue[0])]);
                }
                AssertEx::throws(
                    ParseException::class,
                    function () use ($optParser, $invalidRawValue): void {
                        Parser::parseOptionRawValue($invalidRawValue, $optParser);
                    }
                );
            }
            $dbgCtx->popSubScope();
        } finally {
            $dbgCtx->pop();
        }
    }

    /**
     * @dataProvider allOptionsMetadataWithPossibleInvalidRawValuesProvider
     *
     * @param OptionMetadata<mixed> $optMeta
     */
    public function testParseInvalidValue(string $optMetaDbgDesc, OptionMetadata $optMeta): void
    {
        self::parseInvalidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }

    private static function genOptionalWhitespace(): string
    {
        $whiteSpaceChars = [' ', "\t"];
        $result = '';
        foreach (RangeUtil::generateUpTo(3) as $ignored) {
            $result .= RandomUtil::getRandomValueFromArray($whiteSpaceChars);
        }
        return $result;
    }

    /**
     * @param OptionTestValuesGeneratorInterface<mixed> $testValuesGenerator
     * @param OptionParser<mixed>                       $optParser
     */
    public static function parseValidValueTestImpl(OptionTestValuesGeneratorInterface $testValuesGenerator, OptionParser $optParser): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        $validValues = $testValuesGenerator->validValues();
        if (IterableUtil::isEmpty($validValues)) {
            self::dummyAssert();
            return;
        }
        $dbgCtx->add(['validValues' => $validValues]);

        $valueWithDetails = function (mixed $value): mixed {
            if (!is_float($value)) {
                return $value;
            }

            return ['$value' => $value, 'number_format($value)' => number_format($value)];
        };

        $dbgCtx->pushSubScope();
        /** @var OptionTestValidValue<mixed> $validValueData */
        foreach ($validValues as $validValueData) {
            $dbgCtx->clearCurrentSubScope(['validValueData' => $validValueData, '$validValueData->parsedValue' => $valueWithDetails($validValueData->parsedValue)]);
            $validValueData->rawValue = self::genOptionalWhitespace() . $validValueData->rawValue . self::genOptionalWhitespace();
            $actualParsedValue = Parser::parseOptionRawValue($validValueData->rawValue, $optParser);
            $dbgCtx->add(['actualParsedValue' => $valueWithDetails($actualParsedValue)]);
            AssertEx::equalsEx($validValueData->parsedValue, $actualParsedValue);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @dataProvider allOptionsMetadataProvider
     *
     * @param OptionMetadata<mixed> $optMeta
     */
    public function testParseValidValue(string $optMetaDbgDesc, OptionMetadata $optMeta): void
    {
        self::parseValidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }
}
