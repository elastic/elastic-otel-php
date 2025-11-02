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

use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTests\Util\EnumUtilForTestsTrait;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind as OTelProtoSpanKind;
use PHPUnit\Framework\Assert;

enum SpanKind implements LoggableInterface
{
    use EnumUtilForTestsTrait;

    case unspecified;
    case internal;
    case client;
    case server;
    case producer;
    case consumer;

    private const FROM_OTEL_PROTO_SPAN_KIND = [
        OTelProtoSpanKind::SPAN_KIND_UNSPECIFIED => self::unspecified,
        OTelProtoSpanKind::SPAN_KIND_INTERNAL => self::internal,
        OTelProtoSpanKind::SPAN_KIND_CLIENT => self::client,
        OTelProtoSpanKind::SPAN_KIND_SERVER => self::server,
        OTelProtoSpanKind::SPAN_KIND_PRODUCER => self::producer,
        OTelProtoSpanKind::SPAN_KIND_CONSUMER => self::consumer,
    ];

    public static function fromOTelProtoSpanKind(int $otelProtoSpanKind): self
    {
        if (ArrayUtil::getValueIfKeyExists($otelProtoSpanKind, self::FROM_OTEL_PROTO_SPAN_KIND, /* out */ $result)) {
            return $result;
        }
        Assert::fail('Unexpected span kind: ' . $otelProtoSpanKind);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->name);
    }
}
