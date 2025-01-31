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

use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpSchemes;
use ElasticOTelTests\Util\HttpStatusCodes;

final class HttpAppCodeRequestParams extends AppCodeRequestParams
{
    public const DEFAULT_HTTP_REQUEST_METHOD = HttpMethods::GET;
    public const DEFAULT_HTTP_REQUEST_URL_PATH = '/';

    public string $httpRequestMethod = self::DEFAULT_HTTP_REQUEST_METHOD;
    public UrlParts $urlParts;
    public ?int $expectedHttpResponseStatusCode = HttpStatusCodes::OK;

    public function __construct(HttpServerHandle $httpServerHandle, AppCodeTarget $appCodeTarget)
    {
        parent::__construct($httpServerHandle->spawnedProcessInternalId, $appCodeTarget);

        $this->urlParts = new UrlParts(scheme: HttpSchemes::HTTP, host: HttpServerHandle::CLIENT_LOCALHOST_ADDRESS, port: $httpServerHandle->getMainPort(), path: self::DEFAULT_HTTP_REQUEST_URL_PATH);
    }
}
