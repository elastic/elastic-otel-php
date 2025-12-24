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

use Elastic\OTel\Log\OTelInternalLogLevel;
use Elastic\OTel\Log\PsrLogLevel;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\ReflectionUtil;
use ElasticOTelTests\Util\TestCaseBase;
use OpenTelemetry\API\Behavior\Internal\Logging as OTelInternalLogging;

class OTelInternalLogLevelTest extends TestCaseBase
{
    public function testInSync(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertSame(ReflectionUtil::getConstValue(OTelInternalLogging::class, 'NONE'), OTelInternalLogLevel::none->name);

        AssertEx::sameConstValues(count(PsrLogLevel::cases()) + 1, count(OTelInternalLogLevel::cases()));
        foreach (PsrLogLevel::cases() as $psrLogLevel) {
            self::assertNotNull(OTelInternalLogLevel::tryToFindByName($psrLogLevel->name));
        }

        $otelInternalLoggingLevelsPrivateList = AssertEx::isList(ReflectionUtil::getConstValue(OTelInternalLogging::class, 'LEVELS'));
        $dbgCtx->add(compact('otelInternalLoggingLevelsPrivateList'));
        self::assertCount(count(OTelInternalLogLevel::cases()), $otelInternalLoggingLevelsPrivateList);
        $dbgCtx->pushSubScope();
        foreach ($otelInternalLoggingLevelsPrivateList as $otelInternalLoggingLevelName) {
            $dbgCtx->resetTopSubScope(compact('otelInternalLoggingLevelName'));
            self::assertNotNull(OTelInternalLogLevel::tryToFindByName(AssertEx::isString($otelInternalLoggingLevelName)));
        }
        $dbgCtx->popSubScope();
    }
}
