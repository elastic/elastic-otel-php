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

use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\PHPUnitExtensionBase;
use ElasticOTelTests\Util\TestCaseBase;

class PHPUnitToLogConvertersTest extends TestCaseBase
{
    public function testPHPUnitEvent(): void
    {
        $eventObj = PHPUnitExtensionBase::$lastBeforeTestCaseEvent;
        self::assertNotNull($eventObj);
        $converted = LoggableToString::convert($eventObj);
        self::assertStringContainsString(JsonUtil::adaptStringToSearchInJson(__CLASS__), $converted);
        self::assertStringContainsString(__FUNCTION__, $converted);
    }

    public function testPHPUnitEventCodeTest(): void
    {
        $eventObj = PHPUnitExtensionBase::$lastBeforeTestCaseEvent;
        self::assertNotNull($eventObj);
        $testObj = $eventObj->test();
        $converted = LoggableToString::convert($testObj);
        self::assertStringContainsString(JsonUtil::adaptStringToSearchInJson(__CLASS__), $converted);
        self::assertStringContainsString(__FUNCTION__, $converted);
    }

    public function testPHPUnitTelemetryInfo(): void
    {
        $eventObj = PHPUnitExtensionBase::$lastBeforeTestCaseEvent;
        self::assertNotNull($eventObj);
        $telemetryInfo = $eventObj->telemetryInfo();
        $converted = LoggableToString::convert($telemetryInfo);
        self::assertStringContainsString('time', $converted);
        self::assertStringContainsString('duration', $converted);
        self::assertStringContainsString('memory', $converted);
        self::assertStringContainsString(strval($telemetryInfo->memoryUsage()->bytes()), $converted);
    }

    public function testPHPUnitTelemetryHRTime(): void
    {
        $eventObj = PHPUnitExtensionBase::$lastBeforeTestCaseEvent;
        self::assertNotNull($eventObj);
        $hrTime = $eventObj->telemetryInfo()->time();
        $converted = LoggableToString::convert($hrTime);
        self::assertStringContainsString(strval($hrTime->seconds()), $converted);
        self::assertStringContainsString(strval($hrTime->nanoseconds()), $converted);
    }
}
