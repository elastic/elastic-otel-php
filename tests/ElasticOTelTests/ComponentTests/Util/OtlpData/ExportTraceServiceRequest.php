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

namespace ElasticOTelTests\ComponentTests\Util\OtlpData;

use ElasticOTelTests\Util\IterableUtil;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceRequest as OTelProtoExportTraceServiceRequest;

/**
 * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.8.0/opentelemetry/proto/collector/trace/v1/trace_service.proto#L34
 */
class ExportTraceServiceRequest
{
    /**
     * @param ResourceSpans[] $resourceSpans
     */
    public function __construct(
        public readonly array $resourceSpans,
    ) {
    }

    public static function deserializeFromOTelProto(OTelProtoExportTraceServiceRequest $source): self
    {
        return new self(
            resourceSpans: DeserializationUtil::deserializeArrayFromOTelProto($source->getResourceSpans(), ResourceSpans::deserializeFromOTelProto(...)),
        );
    }

    /**
     * @return iterable<Span>
     */
    public function spans(): iterable
    {
        foreach ($this->resourceSpans as $resourceSpans) {
            yield from $resourceSpans->spans();
        }
    }

    public function isEmptyAfterDeserialization(): bool
    {
        return IterableUtil::isEmpty($this->spans());
    }

    /**
     * @return iterable<OTelResource>
     */
    public function resources(): iterable
    {
        foreach ($this->resourceSpans as $resourceSpans) {
            if ($resourceSpans->resource !== null) {
                yield $resourceSpans->resource;
            }
        }
    }
}
