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

namespace ElasticOTelTests\Util;

use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use PHPUnit\Runner\BeforeTestHook;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class PhpUnitExtensionBase implements BeforeTestHook
{
    public static SystemTime $timestampBeforeTest;
    private readonly Logger $logger;

    public function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
    }

    public function executeBeforeTest(string $test): void
    {
        ExceptionUtil::runCatchLogRethrow(
            function (): void {
                DebugContextForTests::reset();
                self::$timestampBeforeTest = AmbientContextForTests::clock()->getSystemClockCurrentTime();
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->includeStackTrace()->log('', ['timestampBeforeTest' => TimeUtil::timestampToLoggable(self::$timestampBeforeTest->value)]);
            }
        );
    }
}
