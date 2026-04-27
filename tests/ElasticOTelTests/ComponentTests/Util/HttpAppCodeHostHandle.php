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

use Closure;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Override;

class HttpAppCodeHostHandle extends AppCodeHostHandle
{
    private readonly Logger $logger;

    public function __construct(
        TestCaseHandle $testCaseHandle,
        HttpAppCodeHostParams $appCodeHostParams,
        public readonly HttpServerHandle $httpServerHandle
    ) {
        parent::__construct($testCaseHandle, $appCodeHostParams);
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /** @inheritDoc */
    #[Override]
    public function execAppCode(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $logDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $logDebug?->log(__LINE__, 'Sending HTTP request to app code ...', compact('appCodeTarget'));
        $this->sendHttpRequestToAppCode($appCodeTarget, $setParamsFunc);
        $logDebug?->log(__LINE__, 'Sent HTTP request to app code', compact('appCodeTarget'));
    }

    /**
     * @param null|Closure(HttpAppCodeRequestParams): void $setParamsFunc
     */
    private function sendHttpRequestToAppCode(AppCodeTarget $appCodeTarget, ?Closure $setParamsFunc = null): void
    {
        $requestParams = $this->buildRequestParams($appCodeTarget);
        if ($setParamsFunc !== null) {
            $setParamsFunc($requestParams);
        }

        $appCodeInvocation = $this->beforeAppCodeInvocation($requestParams);
        HttpClientUtilForTests::sendRequestToAppCode($requestParams);
        $this->afterAppCodeInvocation($appCodeInvocation);
    }

    public function buildRequestParams(AppCodeTarget $appCodeTarget): HttpAppCodeRequestParams
    {
        return new HttpAppCodeRequestParams($this->httpServerHandle, $appCodeTarget);
    }
}
