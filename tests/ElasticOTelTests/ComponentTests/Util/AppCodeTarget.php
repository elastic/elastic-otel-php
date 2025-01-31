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

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\TestCaseBase;

final class AppCodeTarget implements LoggableInterface
{
    use LoggableTrait;

    public ?string $appCodeClass = null;
    public ?string $appCodeMethod = null;

    /**
     * @param array{class-string, string} $appCodeClassMethod
     */
    public static function asRouted(array $appCodeClassMethod): AppCodeTarget
    {
        TestCaseBase::assertArrayIsList($appCodeClassMethod);
        TestCaseBase::assertCount(2, $appCodeClassMethod); /** @phpstan-ignore staticMethod.alreadyNarrowedType */

        $thisObj = new AppCodeTarget();
        $thisObj->appCodeClass = $appCodeClassMethod[0];
        $thisObj->appCodeMethod = $appCodeClassMethod[1];
        return $thisObj;
    }
}
