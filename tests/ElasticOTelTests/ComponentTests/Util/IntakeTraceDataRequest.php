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

use ElasticOTelTests\ComponentTests\Util\OtlpData\ExportTraceServiceRequest;
use ElasticOTelTests\ComponentTests\Util\OtlpData\OTelResource;
use ElasticOTelTests\ComponentTests\Util\OtlpData\Span;
use ElasticOTelTests\Util\Log\LoggableTrait;
use OpenTelemetry\Contrib\Otlp\ProtobufSerializer;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceRequest as OTelProtoExportTraceServiceRequest;
use Override;

final class IntakeTraceDataRequest extends IntakeDataRequestDeserialized
{
    use LoggableTrait;

    private function __construct(
        IntakeDataRequestRaw $raw,
        private readonly ExportTraceServiceRequest $deserialized,
    ) {
        parent::__construct($raw);
    }

    public static function deserializeFromRaw(IntakeDataRequestRaw $raw): self
    {
        $serializer = ProtobufSerializer::getDefault();
        $otelProtoRequest = new OTelProtoExportTraceServiceRequest();
        $serializer->hydrate($otelProtoRequest, $raw->body);

        return new self($raw, ExportTraceServiceRequest::deserializeFromOTelProto($otelProtoRequest));
    }

    #[Override]
    public function isEmptyAfterDeserialization(): bool
    {
        return $this->deserialized->isEmptyAfterDeserialization();
    }

    /**
     * @return iterable<Span>
     */
    public function spans(): iterable
    {
        yield from $this->deserialized->spans();
    }

    /**
     * @return iterable<OTelResource>
     */
    public function resources(): iterable
    {
        yield from $this->deserialized->resources();
    }
}
