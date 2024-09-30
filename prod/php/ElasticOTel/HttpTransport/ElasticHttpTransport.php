<?php

declare(strict_types=1);

namespace Elastic\OTel\HttpTransport;

use function assert;
use BadMethodCallException;
use function explode;
use function in_array;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use RuntimeException;
use function strtolower;
use Throwable;
use function time_nanosleep;
use function trim;

/**
 * @psalm-template CONTENT_TYPE of string
 * @template-implements TransportInterface<CONTENT_TYPE>
 */
final class ElasticHttpTransport implements TransportInterface
{

    private string $endpoint;
    private string $contentType;

    /**
     * @psalm-param CONTENT_TYPE $contentType
     */
    public function __construct(
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
    ) {
        $this->endpoint = $endpoint;
        $this->contentType = $contentType;

        initialize($endpoint, $contentType, $headers, $timeout, $retryDelay, $maxRetries);
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
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
