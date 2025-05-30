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

namespace ElasticOTelTests\UnitTests\UtilTests;

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTests\Util\TextUtilForTests;
use UnexpectedValueException;

class TextUtilTest extends TestCaseBase
{
    /**
     * @return array<array<string>>
     */
    public static function camelToSnakeCaseTestDataProvider(): array
    {
        return [
            ['', ''],
            ['a', 'a'],
            ['B', 'b'],
            ['1', '1'],
            ['aB', 'a_b'],
            ['AB', 'a_b'],
            ['Ab', 'ab'],
            ['Ab1', 'ab1'],
            ['spanCount', 'span_count'],
        ];
    }

    /**
     * @return iterable<array<string>>
     */
    public static function snakeToCamelCaseTestDataProvider(): iterable
    {
        yield ['', ''];
        yield ['a', 'a'];
        yield ['1', '1'];
        yield ['a_b', 'aB'];
        yield ['a1_b', 'a1B'];
        yield ['span_count', 'spanCount'];
        yield ['_span_count', 'spanCount'];
        yield ['__span__count', 'spanCount'];
        yield ['_', ''];
        yield ['__', ''];
        yield ['_x_', 'x'];
        yield ['x_y', 'xY'];
        yield ['x_1y', 'x1y'];
    }

    /**
     * @dataProvider camelToSnakeCaseTestDataProvider
     *
     * @param string $inputCamelCase
     * @param string $inputSnakeCase
     */
    public function testCamelToSnakeCase(string $inputCamelCase, string $inputSnakeCase): void
    {
        self::assertSame($inputSnakeCase, TextUtil::camelToSnakeCase($inputCamelCase));
    }

    /**
     * @dataProvider snakeToCamelCaseTestDataProvider
     *
     * @param string $inputCamelCase
     * @param string $inputSnakeCase
     */
    public function testSnakeToCamelCase(string $inputSnakeCase, string $inputCamelCase): void
    {
        self::assertSame($inputCamelCase, TextUtil::snakeToCamelCase($inputSnakeCase));
    }

    public function testIsPrefixOf(): void
    {
        self::assertTrue(TextUtil::isPrefixOf('', ''));
        self::assertTrue(TextUtil::isPrefixOf('', '', isCaseSensitive: false));
        self::assertTrue(!TextUtil::isPrefixOf('a', ''));
        self::assertTrue(!TextUtil::isPrefixOf('a', '', isCaseSensitive: false));

        self::assertTrue(TextUtil::isPrefixOf('A', 'ABC'));
        self::assertTrue(!TextUtil::isPrefixOf('a', 'ABC'));
        self::assertTrue(TextUtil::isPrefixOf('a', 'ABC', isCaseSensitive: false));

        self::assertTrue(TextUtil::isPrefixOf('AB', 'ABC'));
        self::assertTrue(!TextUtil::isPrefixOf('aB', 'ABC'));
        self::assertTrue(TextUtil::isPrefixOf('aB', 'ABC', isCaseSensitive: false));

        self::assertTrue(TextUtil::isPrefixOf('ABC', 'ABC'));
        self::assertTrue(!TextUtil::isPrefixOf('aBc', 'ABC'));
        self::assertTrue(TextUtil::isPrefixOf('aBc', 'ABC', isCaseSensitive: false));
    }

    public function testIsSuffixOf(): void
    {
        self::assertTrue(TextUtil::isSuffixOf('', ''));
        self::assertTrue(TextUtil::isSuffixOf('', '', isCaseSensitive: false));
        self::assertTrue(!TextUtil::isSuffixOf('a', ''));
        self::assertTrue(!TextUtil::isSuffixOf('a', '', isCaseSensitive: false));

        self::assertTrue(TextUtil::isSuffixOf('C', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('c', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('c', 'ABC', isCaseSensitive: false));

        self::assertTrue(TextUtil::isSuffixOf('BC', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('Bc', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('Bc', 'ABC', isCaseSensitive: false));

        self::assertTrue(TextUtil::isSuffixOf('ABC', 'ABC'));
        self::assertTrue(!TextUtil::isSuffixOf('aBc', 'ABC'));
        self::assertTrue(TextUtil::isSuffixOf('aBc', 'ABC', isCaseSensitive: false));
    }

    public function testFlipLetterCase(): void
    {
        $flipOneLetterString = function (string $src): string {
            return chr(TextUtil::flipLetterCase(ord($src[0])));
        };

        self::assertSame('a', $flipOneLetterString('A'));
        self::assertNotEquals('A', $flipOneLetterString('A'));
        self::assertSame('X', $flipOneLetterString('x'));
        self::assertNotEquals('x', $flipOneLetterString('x'));
        self::assertSame('0', $flipOneLetterString('0'));
        self::assertSame('#', $flipOneLetterString('#'));
    }
    /**
     * @return iterable<array{string, array{string, string}[]}>
     *                                              ^^^^^^------- end-of-line
     *                                      ^^^^^^--------------- line text without end-of-line
     *                        ^^^^^^----------------------------- input text
     */
    public static function dataProviderForTestIterateLines(): iterable
    {
        yield [
            '' /* empty line without end-of-line */,
            [['' /* <- empty line text */, '' /* <- no end-of-line */]]
        ];

        yield [
            'some text without end-of-line',
            [['some text without end-of-line', '' /* <- no end-of-line */]]
        ];

        yield [
            PHP_EOL /* <- empty line */ .
            'second line' . PHP_EOL .
            PHP_EOL /* <- empty line */ .
            'last non-empty line' . PHP_EOL
            /* empty line without end-of-line */,
            [
                ['' /* <- empty line text */, PHP_EOL],
                ['second line', PHP_EOL],
                ['' /* <- empty line text */, PHP_EOL],
                ['last non-empty line', PHP_EOL],
                ['' /* <- empty line text */, '' /* <- no end-of-line */],
            ],
        ];

        yield ["\n", [['' /* <- empty line text */, "\n"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];
        yield ["\r", [['' /* <- empty line text */, "\r"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];
        yield ["\r\n", [['' /* <- empty line text */, "\r\n"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];

        // "\n\r" is not one line end-of-line but two "\n\r"
        yield ["\n\r", [['' /* <- empty line text */, "\n"], ['' /* <- empty line text */, "\r"], ['' /* <- empty line text */, '']]];
    }

    /**
     * @dataProvider dataProviderForTestIterateLines
     *
     * @param string                  $inputText
     * @param array{string, string}[] $expectedLinesParts
     *                      ^^^^^^------------------------------ end-of-line
     *              ^^^^^^-------------------------------------- line text without end-of-line
     */
    public function testIterateLines(string $inputText, array $expectedLinesParts): void
    {
        foreach ([true, false] as $keepEndOfLine) {
            $index = 0;
            foreach (TextUtilForTests::iterateLines($inputText, $keepEndOfLine) as $actualLine) {
                $expectedLineParts = $expectedLinesParts[ $index ];
                self::assertCount(2, $expectedLineParts); // @phpstan-ignore staticMethod.alreadyNarrowedType
                $expectedLine = $expectedLineParts[0] . ($keepEndOfLine ? $expectedLineParts[1] : '');
                self::assertSame($expectedLine, $actualLine);
                ++$index;
            }
        }
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function dataProviderForTestPrefixEachLine(): iterable
    {
        yield ['', 'p_', 'p_'];
        yield ["\n", 'p_', "p_\np_"];
        yield ["\r", 'p_', "p_\rp_"];
        yield ["\r\n", 'p_', "p_\r\np_"];
        yield ["\n\r", 'p_', "p_\np_\rp_"];
    }

    /**
     * @dataProvider dataProviderForTestPrefixEachLine
     *
     * @param string $inputText
     * @param string $prefix
     * @param string $expectedOutputText
     *
     * @return void
     */
    public function testPrefixEachLine(string $inputText, string $prefix, string $expectedOutputText): void
    {
        $actualOutputText = TextUtilForTests::prefixEachLine($inputText, $prefix);
        self::assertSame($expectedOutputText, $actualOutputText);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function dataProviderForTestRemoveIndentationValidInput(): iterable
    {
        /**
         * @param list<string> $lines
         *
         * @return iterable<array{string, string}>
         */
        $genFromOneIndentationAndLines = function (string $indentation, array $lines): iterable {
            AssertEx::countAtLeast(1, $lines);
            $input = '';
            $expectedOutput = '';
            foreach ([true, false] as $shouldAppendEmptyLine) {
                /** @var non-negative-int $i */
                /** @var string $line */
                foreach (IterableUtil::iterateListWithIndex($lines) as [$i, $line]) {
                    /** @var string $line */
                    $lineSuffix = ($shouldAppendEmptyLine && ($i === (count($lines) - 1))) ? PHP_EOL : '';
                    $input = $indentation . $line . $lineSuffix;
                    $expectedOutput = $line . $lineSuffix;
                }
                yield [$input, $expectedOutput];
            }
        };

        yield from $genFromOneIndentationAndLines(indentation: "", lines: ['']);
        yield from $genFromOneIndentationAndLines(indentation: " ", lines: ['']);
        yield from $genFromOneIndentationAndLines(indentation: "\t", lines: ['']);
        yield from $genFromOneIndentationAndLines(indentation: "\t ", lines: ['']);
        yield from $genFromOneIndentationAndLines(indentation: " \t", lines: ['']);
        yield from $genFromOneIndentationAndLines(indentation: "\t\t", lines: ['a', 'b']);

        yield [
            " " . "a" . "\r\n" .
            " " . "b",

            "a" . "\r\n" .
            "b",
        ];

        yield [
            "\t" . "a" . "\r\n" .
            "\t" . "b",

            "a" . "\r\n" .
            "b",
        ];
    }

    /**
     * @dataProvider dataProviderForTestRemoveIndentationValidInput
     */
    public function testRemoveIndentationValidInput(string $input, string $expectedOutput): void
    {
        $actualOutput = TextUtilForTests::removeIndentation($input);
        self::assertSame($expectedOutput, $actualOutput);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function dataProviderForTestRemoveIndentationInvalidInput(): iterable
    {
        yield [
            "\t" . "a" . "\r\n" .
            "b"
        ];
        yield [
            " " . "abc" . "\n" .
            "abc"
        ];
    }

    /**
     * @dataProvider dataProviderForTestRemoveIndentationInvalidInput
     */
    public function testRemoveIndentationInvalidInput(string $input): void
    {
        AssertEx::throws(UnexpectedValueException::class, fn() => TextUtilForTests::removeIndentation($input));
    }
}
