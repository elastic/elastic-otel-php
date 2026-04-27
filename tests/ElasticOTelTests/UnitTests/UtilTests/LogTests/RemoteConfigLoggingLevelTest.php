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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Log\OTelInternalLogLevel;
use Elastic\OTel\Log\RemoteConfigLoggingLevel;
use ElasticOTelTests\Util\TestCaseBase;

final class RemoteConfigLoggingLevelTest extends TestCaseBase
{
    public function testToElasticLogLevel(): void
    {
        foreach (RemoteConfigLoggingLevel::cases() as $remoteCfgLogLevel) {
            $actualElasticLogLevel = $remoteCfgLogLevel->toElasticLogLevel();
            match ($remoteCfgLogLevel) {
                RemoteConfigLoggingLevel::warn => self::assertSame(LogLevel::warning, $actualElasticLogLevel),
                RemoteConfigLoggingLevel::fatal => self::assertSame(LogLevel::critical, $actualElasticLogLevel),
                default => self::assertSame(LogLevel::tryToFindByName($remoteCfgLogLevel->name), $actualElasticLogLevel),
            };
        }
    }

    public function testToOTelInternalLogLevel(): void
    {
        foreach (RemoteConfigLoggingLevel::cases() as $remoteCfgLogLevel) {
            $actualOTelLogLevel = $remoteCfgLogLevel->toOTelInternalLogLevel();
            match ($remoteCfgLogLevel) {
                RemoteConfigLoggingLevel::trace => self::assertSame(OTelInternalLogLevel::debug, $actualOTelLogLevel),
                RemoteConfigLoggingLevel::warn => self::assertSame(OTelInternalLogLevel::warning, $actualOTelLogLevel),
                RemoteConfigLoggingLevel::fatal => self::assertSame(OTelInternalLogLevel::critical, $actualOTelLogLevel),
                RemoteConfigLoggingLevel::off => self::assertSame(OTelInternalLogLevel::none, $actualOTelLogLevel),
                default => self::assertSame(OTelInternalLogLevel::tryToFindByName($remoteCfgLogLevel->name), $actualOTelLogLevel),
            };
        }
    }
}
