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

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

declare(strict_types=1);

namespace ElasticOTelTests\Util;

use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TestCaseBase extends TestCase
{
    /**
     * @param array<string|int, mixed> $idToXyzMap
     *
     * @return string[]
     */
    public static function getIdsFromIdToMap(array $idToXyzMap): array
    {
        /** @var string[] $result */
        $result = [];
        foreach ($idToXyzMap as $id => $_) {
            $result[] = strval($id);
        }
        return $result;
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     *
     * @return Logger
     */
    public static function getLoggerStatic(string $namespace, string $fqClassName, string $srcCodeFile): Logger
    {
        return AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST, $namespace, $fqClassName, $srcCodeFile);
    }

    public static function dummyAssert(): bool
    {
        Assert::assertTrue(true); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        return true;
    }

    #[Override]
    public function setUp(): void
    {
        parent::setUp();
    }

    #[Override]
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @param iterable<array<array-key, mixed>> $srcDataProvider
     *
     * @return iterable<string, array<array-key, mixed>>
     */
    protected static function wrapDataProviderFromKeyValueMapToNamedDataSet(iterable $srcDataProvider): iterable
    {
        $dataSetIndex = 0;
        foreach ($srcDataProvider as $namedValuesMap) {
            $dataSetName = '#' . $dataSetIndex;
            $dataSetName .= ' ' . LoggableToString::convert($namedValuesMap);
            yield $dataSetName => array_values($namedValuesMap);
            ++$dataSetIndex;
        }
    }

    private const VERY_LONG_STRING_BASE_PREFIX = '<very long string prefix';
    private const VERY_LONG_STRING_BASE_SUFFIX = 'very long string suffix>';

    /**
     * @param positive-int $length
     */
    public static function generateVeryLongString(int $length): string
    {
        $midLength = $length - (strlen(self::VERY_LONG_STRING_BASE_PREFIX) + strlen(self::VERY_LONG_STRING_BASE_SUFFIX));
        Assert::assertGreaterThanOrEqual(0, $midLength);
        return self::VERY_LONG_STRING_BASE_PREFIX . str_repeat('-', $midLength) . self::VERY_LONG_STRING_BASE_SUFFIX;
    }
}
