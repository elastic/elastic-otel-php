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

namespace ElasticOTelTests\UnitTests;

use Elastic\OTel\RemoteConfigHandler;
use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\API\Behavior\Internal\Logging as OTelInternalLogging;
use OpenTelemetry\SDK\Common\Configuration\Variables as OTelSdkConfigVariables;
use OpenTelemetry\SDK\Common\Configuration\KnownValues as OTelSdkConfigKnownValues;
use ReflectionClass;

final class EdotDependenciesOnOTelSdkTest extends TestCaseBase
{
    /**
     * @param class-string<object> $classFqName
     *
     * @noinspection PhpDocMissingThrowsInspection, PhpSameParameterValueInspection
     */
    private static function getPrivateConstValue(string $classFqName, string $constName): mixed
    {
        $reflClass = new ReflectionClass($classFqName);
        self::assertTrue($reflClass->hasConstant($constName));
        return $reflClass->getConstant($constName);
    }

    public function testConstNamesInSync(): void
    {
        self::assertSame(OTelSdkConfigVariables::OTEL_EXPERIMENTAL_CONFIG_FILE, RemoteConfigHandler::OTEL_EXPERIMENTAL_CONFIG_FILE); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertSame(OTelSdkConfigVariables::OTEL_LOG_LEVEL, RemoteConfigHandler::LOG_LEVEL_OTEL_OPTION_NAME); // @phpstan-ignore staticMethod.alreadyNarrowedType

        self::assertSame(self::getPrivateConstValue(OTelInternalLogging::class, 'OTEL_LOG_LEVEL'), RemoteConfigHandler::LOG_LEVEL_OTEL_OPTION_NAME);
        self::assertSame(self::getPrivateConstValue(OTelInternalLogging::class, 'NONE'), RemoteConfigHandler::OTEL_LOG_LEVEL_NONE);

        self::assertSame(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER, RemoteConfigHandler::OTEL_TRACES_SAMPLER); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertSame(OTelSdkConfigVariables::OTEL_TRACES_SAMPLER_ARG, RemoteConfigHandler::OTEL_TRACES_SAMPLER_ARG); // @phpstan-ignore staticMethod.alreadyNarrowedType
        $expected = OTelSdkConfigKnownValues::VALUE_PARENT_BASED_TRACE_ID_RATIO;
        self::assertSame($expected, RemoteConfigHandler::OTEL_TRACES_SAMPLER_VALUE_PARENT_BASED_TRACE_ID_RATIO); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }
}
