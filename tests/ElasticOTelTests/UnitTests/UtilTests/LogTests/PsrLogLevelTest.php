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

use Elastic\OTel\Log\PsrLogLevel;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\TestCaseBase;
use ReflectionClass;
use Psr\Log\LogLevel as PsrLogLogLevelConsts;

class PsrLogLevelTest extends TestCaseBase
{
    public function testMatchesConsts(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $constNames = array_keys((new ReflectionClass(PsrLogLogLevelConsts::class))->getConstants());
        $dbgCtx->add(compact('constNames'));

        $dbgCtx->pushSubScope();
        $constValues = [];
        foreach ($constNames as $constName) {
            $dbgCtx->resetTopSubScope(compact('constName'));
            $constValue = AssertEx::isString(constant(PsrLogLogLevelConsts::class . '::' . $constName));
            self::assertSame($constName, strtoupper($constValue));
            self::assertNotNull(PsrLogLevel::tryToFindByName($constValue));
            $constValues[] = $constValue;
        }
        $dbgCtx->popSubScope();

        AssertEx::equalAsSets($constValues, PsrLogLevel::casesNames());
    }

    public function testTryFindByName(): void
    {
        foreach (PsrLogLevel::cases() as $psrLogLevel) {
            self::assertSame($psrLogLevel, PsrLogLevel::tryToFindByName($psrLogLevel->name));
            self::assertSame($psrLogLevel, PsrLogLevel::tryToFindByName($psrLogLevel->name, isCaseSensitive: true));
            self::assertSame($psrLogLevel, PsrLogLevel::tryToFindByName(strtolower($psrLogLevel->name), isCaseSensitive: true));
            self::assertSame($psrLogLevel, PsrLogLevel::tryToFindByName(strtoupper($psrLogLevel->name)));
            self::assertNull(PsrLogLevel::tryToFindByName(strtoupper($psrLogLevel->name), isCaseSensitive: true));
        }

        self::assertNull(PsrLogLevel::tryToFindByName('dummy'));
        self::assertNull(PsrLogLevel::tryToFindByName(''));
    }
}
