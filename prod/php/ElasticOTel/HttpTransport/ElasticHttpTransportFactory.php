<?php

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
        return new ElasticHttpTransport($endpoint, $contentType, $headers, $compression, $timeout, $retryDelay, $maxRetries, $cacert, $cert, $key);
    }
}
