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

use ElasticOTelTests\Util\AssertEx;
use Opentelemetry\Proto\Resource\V1\Resource as OTelProtoResource;

/**
 * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.8.0/opentelemetry/proto/resource/v1/resource.proto#L28
 */
class OTelResource
{
    /**
     * @param non-negative-int $droppedAttributesCount
     */
    public function __construct(
        public readonly Attributes $attributes,
        public readonly int $droppedAttributesCount,
    ) {
    }

    public static function deserializeFromOTelProto(OTelProtoResource $source): self
    {
        return new self(
            attributes: Attributes::deserializeFromOTelProto($source->getAttributes()),
            droppedAttributesCount: AssertEx::isNonNegativeInt($source->getDroppedAttributesCount()),
        );
    }
}
