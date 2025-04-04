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

namespace OpenTelemetry\Contrib\Otlp;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use Opentelemetry\Proto\Collector\Metrics\V1\ExportMetricsServiceResponse;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Metrics\AggregationTemporalitySelectorInterface;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricMetadataInterface;
use OpenTelemetry\SDK\Metrics\PushMetricExporterInterface;
use Throwable;

use function Elastic\OTel\OtlpExporters\convert_metrics;

/**
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk_exporters/stdout.md#opentelemetry-metrics-exporter---standard-output
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/protocol/file-exporter.md#json-file-serialization
 * @psalm-import-type SUPPORTED_CONTENT_TYPES from ProtobufSerializer
 */
final class MetricExporter implements PushMetricExporterInterface, AggregationTemporalitySelectorInterface
{
    use LogsMessagesTrait;

    /**
     * @psalm-param TransportInterface<SUPPORTED_CONTENT_TYPES> $transport
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string|Temporality|null $temporality = null,
    ) {
    }

    /** @phpstan-ignore-next-line */
    public function temporality(MetricMetadataInterface $metric): Temporality|string|null
    {
        return $this->temporality ?? $metric->temporality();
    }

    /** @inheritDoc */
    public function export(iterable $batch): bool
    {
        return $this->transport
            ->send(convert_metrics($batch))
            ->map(
                static function (mixed $payload): bool {
                    if ($payload === null) {
                        return true;
                    }

                    $serviceResponse = new ExportMetricsServiceResponse();

                    $partialSuccess = $serviceResponse->getPartialSuccess();
                    if ($partialSuccess !== null && $partialSuccess->getRejectedDataPoints()) {
                        self::logError('Export partial success', [
                            'rejected_data_points' => $partialSuccess->getRejectedDataPoints(),
                            'error_message'        => $partialSuccess->getErrorMessage(),
                        ]);

                        return false;
                    }
                    if ($partialSuccess !== null && $partialSuccess->getErrorMessage()) {
                        self::logWarning('Export success with warnings/suggestions', ['error_message' => $partialSuccess->getErrorMessage()]);
                    }

                    return true;
                }
            )->catch(
                static function (Throwable $throwable): bool {
                    self::logError('Export failure', ['exception' => $throwable]);

                    return false;
                }
            )->await();
    }

    public function shutdown(): bool
    {
        return $this->transport->shutdown();
    }

    public function forceFlush(): bool
    {
        return $this->transport->forceFlush();
    }
}
