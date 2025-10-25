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

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;

final class HelperSleepsAndExitsWithArgCode extends SpawnedProcessBase
{
    private readonly Logger $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Done');
    }

    #[Override]
    protected function processConfig(): void
    {
        parent::processConfig();
        AmbientContextForTests::testConfig()->validateForAppCode();
    }

    public static function run(): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObj): void {
                Assert::assertInstanceOf(self::class, $thisObj);
                $thisObj->runImpl();
            }
        );
    }

    #[Override]
    protected function isThisProcessTestScoped(): bool
    {
        return true;
    }

    private function runImpl(): never
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        Assert::assertSame('cli', php_sapi_name());

        /**
         * @see https://www.php.net/manual/en/reserved.variables.argv.php
         *
         * $argv
         * Note: This variable is not available when register_argc_argv is disabled.
         *
         * @see https://www.php.net/manual/en/ini.core.php#ini.register-argc-argv
         */
        Assert::assertTrue(BoolUtil::fromString(AssertEx::isString(ini_get('register_argc_argv'))));

        /** @var list<string> $argv */
        global $argv;
        $dbgCtx->add(compact('argv'));
        AssertEx::countAtLeast(3, $argv);

        $secondsToSleep = AssertEx::stringIsInt($argv[1]);
        $exitCodeToExit = AssertEx::stringIsInt($argv[2]);

        echo basename(__FILE__) . ": Sleeping: $secondsToSleep seconds..." . PHP_EOL;
        sleep($secondsToSleep);

        echo basename(__FILE__) . ": Exiting with code: $exitCodeToExit" . PHP_EOL;
        exit($exitCodeToExit);
    }
}
