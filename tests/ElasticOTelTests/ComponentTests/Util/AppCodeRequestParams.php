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
use ElasticOTelTests\Util\MixedMap;

class AppCodeRequestParams implements LoggableInterface
{
    use LoggableTrait;

    public TestInfraDataPerRequest $dataPerRequest;

    public function __construct(string $spawnedProcessInternalId, AppCodeTarget $appCodeTarget)
    {
        $this->dataPerRequest = new TestInfraDataPerRequest(spawnedProcessInternalId: $spawnedProcessInternalId, appCodeTarget: $appCodeTarget);
    }

    /**
     * @param MixedMap|array<string, mixed> $appCodeArgs
     */
    public function setAppCodeArgs(MixedMap|array $appCodeArgs): void
    {
        $this->dataPerRequest->appCodeArguments = $appCodeArgs instanceof MixedMap ? $appCodeArgs->cloneAsArray() : $appCodeArgs;
    }

    /** @noinspection PhpUnused */
    public function setIsAppCodeExpectedToThrow(bool $isAppCodeExpectedToThrow): void
    {
        $this->dataPerRequest->isAppCodeExpectedToThrow = $isAppCodeExpectedToThrow;
    }
}
