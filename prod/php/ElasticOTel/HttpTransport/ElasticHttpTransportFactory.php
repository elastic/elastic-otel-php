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

namespace Elastic\OTel\HttpTransport;

use Elastic\OTel\HttpTransport\ElasticHttpTransport;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;

class ElasticHttpTransportFactory implements TransportFactoryInterface
{
    private const DEFAULT_COMPRESSION = 'none';

    public function create(
        string $endpoint,
        string $contentType,
        array $headers = [],
        $compression = null,
        float $timeout = 10.,
        int $retryDelay = 100,
        int $maxRetries = 3,
        ?string $cacert = null,
        ?string $cert = null,
        ?string $key = null
    ): ElasticHttpTransport {
        spl_autoload_call("Elastic\OTel\PhpPartVersion");
        $headers['User-Agent'] = "elastic-otlp-http-php/" . \Elastic\OTel\ELASTIC_OTEL_PHP_VERSION;
        return new ElasticHttpTransport($endpoint, $contentType, $headers, $compression, $timeout, $retryDelay, $maxRetries, $cacert, $cert, $key);
    }
}
