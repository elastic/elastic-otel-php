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

namespace ElasticOTelTests\UnitTests\UtilTests\LogTests;

use BackedEnum;
use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\BootstrapTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\FloatLimits;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\Backend as LogBackend;
use ElasticOTelTests\Util\Log\LogConsts;
use ElasticOTelTests\Util\Log\LoggableToEncodedJson;
use ElasticOTelTests\Util\Log\LoggableToJsonEncodable;
use ElasticOTelTests\Util\Log\LoggerFactory;
use ElasticOTelTests\Util\Log\NoopLogSink;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\RangeUtil;
use ElasticOTelTests\Util\TestCaseBase;
use UnitEnum;

class LoggingVariousTypesTest extends TestCaseBase
{
    public static function logValueAndDecodeToJson(mixed $valueToLog): mixed
    {
        return JsonUtil::decode(LoggableToEncodedJson::convert($valueToLog), asAssocArray: true);
    }

    public static function logValueAndVerify(mixed $valueToLog, mixed $expectedValue): void
    {
        $actualValue = self::logValueAndDecodeToJson($valueToLog);
        if (is_float($expectedValue) && is_int($actualValue)) {
            $actualValue = floatval($actualValue);
        }

        if (is_array($expectedValue)) {
            self::assertEquals($expectedValue, $actualValue);
        } else {
            self::assertSame($expectedValue, $actualValue);
        }
    }

    public function testNull(): void
    {
        self::logValueAndVerify(null, null);
    }

    public function testBool(): void
    {
        foreach ([false, true] as $value) {
            self::logValueAndVerify($value, $value);
        }
    }

    public function testInt(): void
    {
        foreach ([0, 1, -1, 123, -654, PHP_INT_MAX, PHP_INT_MIN] as $value) {
            self::logValueAndVerify($value, $value);
        }
    }

    public function testFloat(): void
    {
        $valuesToTest = [0.0, 1.1, -2.5, 4987.41, -654.112255];
        $valuesToTest += [floatval(PHP_INT_MAX), floatval(PHP_INT_MIN)];
        $valuesToTest += [FloatLimits::MIN, FloatLimits::MAX, PHP_FLOAT_MIN];
        foreach ($valuesToTest as $value) {
            self::logValueAndVerify($value, $value);
        }
    }

    public function testString(): void
    {
        $valuesToTest = ['', 'a', 'ABC', "@#$%&*()<>{}[]+-=_~^ \t\r\n,:;.!?"];
        foreach ($valuesToTest as $value) {
            self::logValueAndVerify($value, $value);
        }
    }

    public function testEnum(): void
    {
        /** @var list<array{UnitEnum, string}> $enumObjAndLoggedStringPairs */
        $enumObjAndLoggedStringPairs = [
            [DummyEnum::small, DummyEnum::class . '(small)'],
            [DummyEnum::medium, DummyEnum::class . '(medium)'],
            [DummyEnum::large, DummyEnum::class . '(large)'],
        ];
        foreach ($enumObjAndLoggedStringPairs as $enumObjAndLoggedStringPair) {
            self::logValueAndVerify($enumObjAndLoggedStringPair[0], $enumObjAndLoggedStringPair[1]);
        }
    }

    public function testBackedEnum(): void
    {
        /** @var list<array{BackedEnum, string}> $enumObjAndLoggedStringPairs */
        $enumObjAndLoggedStringPairs = [
            [DummyBackedEnum::hearts, DummyBackedEnum::class . '(hearts(H))'],
            [DummyBackedEnum::diamonds, DummyBackedEnum::class . '(diamonds(D))'],
            [DummyBackedEnum::clubs, DummyBackedEnum::class . '(clubs(C))'],
            [DummyBackedEnum::spades, DummyBackedEnum::class . '(spades(S))'],
        ];
        foreach ($enumObjAndLoggedStringPairs as $enumObjAndLoggedStringPair) {
            self::logValueAndVerify($enumObjAndLoggedStringPair[0], $enumObjAndLoggedStringPair[1]);
        }
    }

    // public function testResource(): void
    // {
    //     // $tmpFile = tmpfile()
    //     // self::logValueAndVerify(null, null);
    // }
    //
    // public function testListArray(): void
    // {
    //     // new SimpleObjectForTests()
    // }
    //
    // public function testMapArray(): void
    // {
    //     // new SimpleObjectForTests()
    // }

    /**
     * @return array<string, mixed>
     */
    private static function expectedSimpleObject(?string $className = null, bool $isPropExcluded = true, mixed $lateInitPropVal = LogConsts::UNINITIALIZED_PROPERTY_SUBSTITUTE): array
    {
        return ($className === null ? [] : [LogConsts::TYPE_KEY => $className])
               + [
                   'intProp'            => 123,
                   'stringProp'         => 'Abc',
                   'nullableStringProp' => null,
                   'lateInitProp'       => $lateInitPropVal,
                   'recursiveProp'      => null,
               ]
               + ($isPropExcluded ? [] : ['excludedProp' => 'excludedProp value']);
    }

    /**
     * @param string|null $className
     * @param bool        $isPropExcluded
     *
     * @return array<string, mixed>
     */
    private static function expectedDerivedSimpleObject(?string $className = null, bool $isPropExcluded = true): array
    {
        return self::expectedSimpleObject($className, $isPropExcluded)
               + ['derivedFloatProp' => 1.5]
               + ($isPropExcluded ? [] : ['anotherExcludedProp' => 'anotherExcludedProp value']);
    }

    public function tearDown(): void
    {
        ObjectForLoggableTraitTests::logWithoutClassName();
        ObjectForLoggableTraitTests::shouldExcludeProp();
        DerivedObjectForLoggableTraitTests::logWithoutClassName();
        DerivedObjectForLoggableTraitTests::shouldExcludeProp();

        parent::tearDown();
    }

    public function testObject(): void
    {
        self::logValueAndVerify(new ObjectForLoggableTraitTests(), self::expectedSimpleObject());

        ObjectForLoggableTraitTests::logWithShortClassName();

        self::logValueAndVerify(new ObjectForLoggableTraitTests(), self::expectedSimpleObject(className: 'ObjectForLoggableTraitTests'));

        ObjectForLoggableTraitTests::logWithCustomClassName('My-custom-type');

        self::logValueAndVerify(
            new ObjectForLoggableTraitTests(),
            self::expectedSimpleObject(className: 'My-custom-type')
        );

        ObjectForLoggableTraitTests::logWithoutClassName();
        ObjectForLoggableTraitTests::shouldExcludeProp(false);

        self::logValueAndVerify(
            new ObjectForLoggableTraitTests(),
            self::expectedSimpleObject(isPropExcluded: false)
        );
    }

    public function testDerivedObject(): void
    {
        self::logValueAndVerify(new DerivedObjectForLoggableTraitTests(), self::expectedDerivedSimpleObject());

        DerivedObjectForLoggableTraitTests::logWithShortClassName();

        self::logValueAndVerify(
            new DerivedObjectForLoggableTraitTests(),
            self::expectedDerivedSimpleObject(className: 'DerivedObjectForLoggableTraitTests')
        );

        DerivedObjectForLoggableTraitTests::logWithCustomClassName('My-custom-type');

        self::logValueAndVerify(
            new DerivedObjectForLoggableTraitTests(),
            self::expectedDerivedSimpleObject(className: 'My-custom-type')
        );

        DerivedObjectForLoggableTraitTests::logWithoutClassName();
        DerivedObjectForLoggableTraitTests::shouldExcludeProp(false);

        self::logValueAndVerify(
            new DerivedObjectForLoggableTraitTests(),
            self::expectedDerivedSimpleObject(isPropExcluded: false)
        );
    }

    // public function testObjectWithThrowingToString(): void
    // {
    //     // new SimpleObjectForTests()
    // }
    //
    // public function testObjectWithDebugInfo(): void
    // {
    //     // new SimpleObjectForTests()
    // }
    //
    // public function testThrowable(): void
    // {
    //     // new SimpleObjectForTests()
    // }

    public function testNoopObject(): void
    {
        self::logValueAndVerify(NoopLogSink::singletonInstance(), [LogConsts::TYPE_KEY => 'NoopLogSink']);
    }

    public function testLogBackend(): void
    {
        self::logValueAndVerify(
            new LogBackend(LogLevel::warning, NoopLogSink::singletonInstance()),
            [
                'maxEnabledLevel' => 'WARNING',
                'logSink'         => NoopLogSink::class,
            ]
        );
    }

    public function testLogger(): void
    {
        $loggerFactory = new LoggerFactory(new LogBackend(LogLevel::debug, NoopLogSink::singletonInstance()));
        $category = 'test category';
        $namespace = 'test namespace';
        $fqClassName = __CLASS__;
        $srcCodeFile = 'test source code file';
        self::logValueAndVerify(
            $loggerFactory->loggerForClass($category, $namespace, $fqClassName, $srcCodeFile),
            [
                'category'       => $category,
                'count(context)' => 0,
                'fqClassName'    => $fqClassName,
                'inheritedData'  => null,
                'namespace'      => $namespace,
                'srcCodeFile'    => $srcCodeFile,
                'backend'        => ['maxEnabledLevel' => 'DEBUG', 'logSink' => NoopLogSink::class],
            ]
        );
    }

    public function testLateInit(): void
    {
        $obj = new ObjectForLoggableTraitTests();
        self::logValueAndVerify($obj, self::expectedSimpleObject());
        $lateInitPropVal = 'late inited value';
        $obj->lateInitProp = $lateInitPropVal;
        self::logValueAndVerify($obj, self::expectedSimpleObject(lateInitPropVal: 'late inited value'));
    }

    private const MAX_DEPTH_KEY = 'max_depth';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestMaxDepth(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generateDataSets = function (): iterable {
            $maxDepthVariants = [0, 1, 2, 3, 10, 15, 20];
            $maxDepthVariants[] = LoggableToJsonEncodable::MAX_DEPTH_IN_PROD_MODE;
            $maxDepthVariants[] = BootstrapTests::LOG_COMPOSITE_DATA_MAX_DEPTH_IN_TEST_MODE;
            $maxDepthVariants = array_unique($maxDepthVariants, SORT_NUMERIC);
            asort(/* ref */ $maxDepthVariants, SORT_NUMERIC);
            foreach ($maxDepthVariants as $maxDepth) {
                yield [self::MAX_DEPTH_KEY => $maxDepth];
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($generateDataSets);
    }

    /**
     * @dataProvider dataProviderForTestMaxDepth
     */
    public function testMaxDepthForScalar(MixedMap $testArgs): void
    {
        $maxDepth = $testArgs->getInt(self::MAX_DEPTH_KEY);
        $savedMaxDepth = LoggableToJsonEncodable::$maxDepth;
        try {
            LoggableToJsonEncodable::$maxDepth = $maxDepth;
            self::assertSame(null, LoggableToJsonEncodable::convert(null, 0));
            self::assertSame(123, LoggableToJsonEncodable::convert(123, 0));
            self::assertSame(654.5, LoggableToJsonEncodable::convert(654.5, 0));
            self::assertSame('my string', LoggableToJsonEncodable::convert('my string', 0));
        } finally {
            LoggableToJsonEncodable::$maxDepth = $savedMaxDepth;
        }
    }

    /**
     * @dataProvider dataProviderForTestMaxDepth
     */
    public function testMaxDepthForArray(MixedMap $testArgs): void
    {
        $maxDepth = $testArgs->getInt(self::MAX_DEPTH_KEY);
        $savedMaxDepth = LoggableToJsonEncodable::$maxDepth;
        try {
            LoggableToJsonEncodable::$maxDepth = $maxDepth;
            self::implTestMaxDepthForArray(LoggableToJsonEncodable::$maxDepth);
        } finally {
            LoggableToJsonEncodable::$maxDepth = $savedMaxDepth;
        }
    }

    private static function implTestMaxDepthForArray(int $maxDepth): void
    {
        /**
         * @param array<string, mixed> $currentArray
         * @param int                  $depth
         *
         * @return array<string, mixed>
         */
        $buildParentArray = function (array $currentArray, int $depth): array {
            $parentArray = [];
            $parentArray['depth ' . $depth . ' int'] = $depth * 10;
            $parentArray['depth ' . $depth . ' string'] = strval($depth * 10);
            $parentArray['depth ' . $depth . ' array'] = $currentArray;
            return $parentArray;
        };

        $arrayToLog = [];
        foreach (RangeUtil::generateDownFrom($maxDepth + 2) as $depth) {
            $arrayToLog = $buildParentArray($arrayToLog, $depth);
        }

        $decodedLoggedArray = self::logValueAndDecodeToJson($arrayToLog);
        $currentLoggedArray = $decodedLoggedArray;
        foreach (RangeUtil::generateUpTo($maxDepth + 2) as $depth) {
            self::assertIsArray($currentLoggedArray);
            self::assertLessThanOrEqual($maxDepth, $depth);

            if ($depth === $maxDepth) {
                AssertEx::equalMaps(LoggableToJsonEncodable::convertArrayForMaxDepth($buildParentArray([], $maxDepth), $maxDepth), $currentLoggedArray);
                break;
            }

            AssertEx::arrayHasKeyWithSameValue('depth ' . $depth . ' int', $depth * 10, $currentLoggedArray);
            AssertEx::arrayHasKeyWithSameValue('depth ' . $depth . ' string', strval($depth * 10), $currentLoggedArray);

            $key = 'depth ' . $depth . ' array';
            self::assertArrayHasKey($key, $currentLoggedArray);
            $currentLoggedArray = $currentLoggedArray[$key];
        }
    }

    /**
     * @dataProvider dataProviderForTestMaxDepth
     */
    public function testMaxDepthForObject(MixedMap $testArgs): void
    {
        $maxDepth = $testArgs->getInt(self::MAX_DEPTH_KEY);
        $savedMaxDepth = LoggableToJsonEncodable::$maxDepth;
        try {
            LoggableToJsonEncodable::$maxDepth = $maxDepth;
            self::implTestMaxDepthForObject($maxDepth);
        } finally {
            LoggableToJsonEncodable::$maxDepth = $savedMaxDepth;
        }
    }

    private static function implTestMaxDepthForObject(int $maxDepth): void
    {
        $buildParentObject = function (ObjectForLoggableTraitTests $currentObject, int $depth) use ($maxDepth): ObjectForLoggableTraitTests {
            return new ObjectForLoggableTraitTests($depth, 'depth: ' . $depth . ', maxDepth: ' . $maxDepth, $currentObject);
        };

        $objectToLog = new ObjectForLoggableTraitTests();
        foreach (RangeUtil::generateDownFrom($maxDepth + 2) as $depth) {
            $objectToLog = $buildParentObject($objectToLog, $depth);
        }

        $decodedLoggedObject = self::logValueAndDecodeToJson($objectToLog);
        $currentLoggedObject = $decodedLoggedObject;
        foreach (RangeUtil::generateUpTo($maxDepth + 2) as $depth) {
            self::assertIsArray($currentLoggedObject);
            self::assertLessThanOrEqual($maxDepth, $depth);

            if ($depth === $maxDepth) {
                AssertEx::equalMaps(LoggableToJsonEncodable::convertObjectForMaxDepth($buildParentObject(new ObjectForLoggableTraitTests(), $maxDepth), $maxDepth), $currentLoggedObject);
                break;
            }

            AssertEx::arrayHasKeyWithSameValue('intProp', $depth, $currentLoggedObject);
            AssertEx::arrayHasKeyWithSameValue('stringProp', 'depth: ' . $depth . ', maxDepth: ' . $maxDepth, $currentLoggedObject);

            $key = 'recursiveProp';
            self::assertArrayHasKey($key, $currentLoggedObject);
            $currentLoggedObject = $currentLoggedObject[$key];
        }
    }
}
