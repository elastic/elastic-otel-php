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

use Elastic\OTel\Log\LogFeature;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTools\ToolsLog;
use ReflectionClass;

final class ToolsUtilTest extends TestCaseBase
{
    public static function testProdLogFeatureValueToNameMap(): void
    {
        $logFeatureValueToNameMap = AssertEx::notEmptyArray(ToolsLog::buildProdLogFeatureValueToNameMap());

        $assertValueToName = function (int $value, string $expectedName) use ($logFeatureValueToNameMap): void {
            self::assertSame($expectedName, AssertEx::arrayHasKey($value, $logFeatureValueToNameMap));
        };

        $assertValueToName(LogFeature::ALL, 'ALL');
        $assertValueToName(LogFeature::CONFIG, 'CONFIG');

        $logFeatureConstNames = array_keys((new ReflectionClass(LogFeature::class))->getConstants());
        foreach ($logFeatureConstNames as $logFeatureConstName) {
            $assertValueToName(AssertEx::isInt(constant(LogFeature::class . '::' . $logFeatureConstName)), $logFeatureConstName);
        }

        self::assertArrayNotHasKey('dummy name', $logFeatureValueToNameMap);
    }
}
