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
use Opentelemetry\Proto\Collector\Logs\V1\ExportLogsServiceResponse;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use Throwable;

use function Elastic\OTel\OtlpExporters\convert_logs;

/**
 * @psalm-import-type SUPPORTED_CONTENT_TYPES from ProtobufSerializer
 */
class LogsExporter implements LogRecordExporterInterface
{
    use LogsMessagesTrait;

    /**
     * @psalm-param TransportInterface<SUPPORTED_CONTENT_TYPES> $transport
     */
    public function __construct(
        private readonly TransportInterface $transport
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return FutureInterface<mixed>
     */
    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        return $this->transport
            ->send(convert_logs($batch), $cancellation)
            ->map(
                static function (mixed $payload): bool {
                    if ($payload === null) {
                        return true;
                    }

                    $serviceResponse = new ExportLogsServiceResponse();

                    $partialSuccess = $serviceResponse->getPartialSuccess();
                    if ($partialSuccess !== null && $partialSuccess->getRejectedLogRecords()) {
                        self::logError('Export partial success', [
                            'rejected_logs' => $partialSuccess->getRejectedLogRecords(),
                            'error_message' => $partialSuccess->getErrorMessage(),
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
            );
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->forceFlush($cancellation);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->shutdown($cancellation);
    }
}
