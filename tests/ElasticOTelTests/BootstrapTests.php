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

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace ElasticOTelTests;

use Elastic\OTel\PhpPartFacade;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LoggableToJsonEncodable;
use ElasticOTelTests\Util\Log\LoggingSubsystem;
use PHPUnit\Framework\Assert;

final class BootstrapTests
{
    use StaticClassTrait;

    public const UNIT_TESTS_DBG_PROCESS_NAME = 'Unit tests';
    public const COMPONENT_TESTS_DBG_PROCESS_NAME = 'Component tests';

    public const LOG_COMPOSITE_DATA_MAX_DEPTH_IN_TEST_MODE = 15;

    private static function bootstrapShared(string $dbgProcessName): void
    {
        AmbientContextForTests::init($dbgProcessName);

        LoggingSubsystem::$isInTestingContext = true;
        LoggableToJsonEncodable::$maxDepth = self::LOG_COMPOSITE_DATA_MAX_DEPTH_IN_TEST_MODE;

        DebugContext::ensureInited();

        // PHP part of EDOT should not be loaded in the tests context
        Assert::assertFalse(PhpPartFacade::$wasBootstrapCalled);
    }

    public static function bootstrapUnitTests(): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function (): void {
                self::bootstrapShared(self::UNIT_TESTS_DBG_PROCESS_NAME);
            }
        );
    }

    public static function bootstrapComponentTests(): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function (): void {
                self::bootstrapShared(self::COMPONENT_TESTS_DBG_PROCESS_NAME);
                AmbientContextForTests::testConfig()->validateForComponentTests();
            }
        );
    }
}
