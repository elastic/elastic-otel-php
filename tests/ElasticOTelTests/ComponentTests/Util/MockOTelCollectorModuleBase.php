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

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\HttpContentTypes;
use ElasticOTelTests\Util\HttpHeaderNames;
use ElasticOTelTests\Util\HttpMethods;
use ElasticOTelTests\Util\HttpStatusCodes;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

abstract class MockOTelCollectorModuleBase implements LoggableInterface
{
    use HttpServerProcessTrait;
    use LoggableTrait;

    protected function __construct(
        protected readonly MockOTelCollector $parent,
    ) {
    }

    /**
     * @return null|ResponseInterface|Promise<ResponseInterface>
     */
    abstract public function processRequest(ServerRequestInterface $request): null|ResponseInterface|Promise;

    protected static function verifyPostProtoBufRequest(ServerRequestInterface $request, int $bodySize): ?ResponseInterface
    {
        if ($request->getMethod() !== HttpMethods::POST) {
            return self::buildErrorResponse(HttpStatusCodes::METHOD_NOT_ALLOWED, 'Method ' . $request->getMethod() . ' is not supported');
        }

        /** @var array<string, array<string>> $httpHeaders */
        $httpHeaders = $request->getHeaders();
        if (($contentLength = AssertEx::stringIsInt(HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_LENGTH, $httpHeaders))) !== $bodySize) {
            return self::buildErrorResponse(
                HttpStatusCodes::BAD_REQUEST,
                'Value in ' . HttpHeaderNames::CONTENT_LENGTH . ' header does not match request body size'
                . '; ' . HttpHeaderNames::CONTENT_LENGTH . ': ' . $contentLength
                . "; request body size: $bodySize"
            );
        }

        if (($contentType = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_TYPE, $httpHeaders)) !== HttpContentTypes::PROTOBUF) {
            return self::buildErrorResponse(HttpStatusCodes::BAD_REQUEST, 'Unexpected ' . HttpHeaderNames::CONTENT_TYPE . ': ' . $contentType);
        }

        return null;
    }

    /**
     * @inheritDoc
     *
     * @return string[]
     */
    #[Override]
    protected static function propertiesExcludedFromLog(): array
    {
        return ['parent'];
    }
}
