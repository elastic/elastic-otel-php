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

use Opentelemetry\Proto\Trace\V1\ResourceSpans as OTelProtoResourceSpans;

/**
 * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.8.0/opentelemetry/proto/trace/v1/trace.proto#L48
 */
class ResourceSpans
{
    /**
     * @param ScopeSpans[] $scopeSpans
     *
     * This schema_url applies to the data in the "resource" field.
     * It does not apply to the data in the "scope_spans" field which have their own schema_url field.
     */
    public function __construct(
        public readonly ?OTelResource $resource,
        public readonly array $scopeSpans,
        public readonly string $schemaUrl,
    ) {
    }

    public static function deserializeFromOTelProto(OTelProtoResourceSpans $source): self
    {
        return new self(
            resource: DeserializationUtil::deserializeNullableFromOTelProto($source->getResource(), OTelResource::deserializeFromOTelProto(...)),
            scopeSpans: DeserializationUtil::deserializeArrayFromOTelProto($source->getScopeSpans(), ScopeSpans::deserializeFromOTelProto(...)),
            schemaUrl: $source->getSchemaUrl(),
        );
    }

    /**
     * @return iterable<Span>
     */
    public function spans(): iterable
    {
        foreach ($this->scopeSpans as $scopeSpans) {
            yield from $scopeSpans->spans;
        }
    }
}
