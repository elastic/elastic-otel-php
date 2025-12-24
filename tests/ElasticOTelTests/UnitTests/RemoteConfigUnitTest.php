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

namespace ElasticOTelTests\UnitTests;

use Elastic\OTel\RemoteConfigHandler;
use ElasticOTelTests\Util\TestCaseBase;

final class RemoteConfigUnitTest extends TestCaseBase
{
    public function testMergeDisabledInstrumentations(): void
    {
        $impl = function (string $localVal, string $remoteVal, string $expectedMergedVal): void {
            $actualMergedVal = RemoteConfigHandler::mergeDisabledInstrumentations($localVal, $remoteVal);
            self::assertSame($expectedMergedVal, $actualMergedVal);
        };

        $impl('', '', '');
        $impl("\t", " \n ", '');
        $impl('a', '', 'a');
        $impl('', 'b', 'b');
        $impl('a', 'b', 'a,b');
        $impl('1,b', 'c,4', '1,b,c,4');
        $impl("1\n, b", "\t c, 4", '1,b,c,4');
    }
}
