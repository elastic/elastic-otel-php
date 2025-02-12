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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel\HttpTransport;

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;

/**
 * @template-implements TransportInterface<string>
 */
final class ElasticHttpTransport implements TransportInterface
{
    private string $endpoint;
    private string $contentType;

    /**
     * @param array<string,string|string[]> $headers
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * Parameters $compression, $cacert, $cert and $key are unused so constructor.unusedParameter is mentioned 4 times below
     * @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter, constructor.unusedParameter, constructor.unusedParameter
     */
    public function __construct(
        string $endpoint,
        string $contentType,
        array $headers = [],
        mixed $compression = null,
        float $timeout = 10.,
        int $retryDelay = 100,
        int $maxRetries = 3,
        ?string $cacert = null,
        ?string $cert = null,
        ?string $key = null
    ) {
        $this->endpoint = $endpoint;
        $this->contentType = $contentType;

        // \Elastic\OTel\HttpTransport\initialize is provided by the extension
        initialize($endpoint, $contentType, $headers, $timeout, $retryDelay, $maxRetries);
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return FutureInterface<null>
     */
    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
        // \Elastic\OTel\HttpTransport\enqueue is provided by the extension
        enqueue($this->endpoint, $payload);

        return new CompletedFuture(null);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }
}
