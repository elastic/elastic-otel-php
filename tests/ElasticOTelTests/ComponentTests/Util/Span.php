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

namespace ElasticOTelTests\ComponentTests\Util;

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\FlagsUtil;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use ElasticOTelTests\Util\TextUtilForTests;
use Opentelemetry\Proto\Trace\V1\Span as OTelProtoSpan;
use Opentelemetry\Proto\Trace\V1\SpanFlags as OTelProtoSpanFlags;
use PHPUnit\Framework\Assert;

final class Span implements LoggableInterface
{
    use LoggableTrait;

    public readonly SpanAttributes $attributes;
    public readonly string $id;
    public readonly SpanKind $kind;
    public readonly string $name;
    public readonly ?string $parentId;
    public readonly string $traceId;
    public readonly int $flags;
    public readonly float $startTimeUnixNano;
    public readonly float $endTimeUnixNano;

    public function __construct(OTelProtoSpan $protoSpan)
    {
        /**
         * @noinspection PhpParamsInspection
         * Google\Protobuf\Internal\RepeatedField is deprecated, and Google\Protobuf\RepeatedField is used instead.
         */
        $this->attributes = new SpanAttributes($protoSpan->getAttributes()); // @phpstan-ignore argument.type
        $this->id = self::convertId($protoSpan->getSpanId());
        $this->kind = SpanKind::fromOTelProtoSpanKind($protoSpan->getKind());
        $this->name = $protoSpan->getName();
        $this->parentId = self::convertNullableId($protoSpan->getParentSpanId());
        $this->traceId = self::convertId($protoSpan->getTraceId());
        $this->flags = $protoSpan->getFlags();
        $this->startTimeUnixNano = self::convertTimeUnixNano($protoSpan->getStartTimeUnixNano());
        $this->endTimeUnixNano = self::convertTimeUnixNano($protoSpan->getEndTimeUnixNano());
    }

    private static function convertNullableId(string $binaryId): ?string
    {
        return TextUtil::isEmptyString($binaryId) ? null : self::convertId($binaryId);
    }

    private static function convertId(string $binaryId): string
    {
        Assert::assertFalse(TextUtil::isEmptyString($binaryId));

        /** @var int[] $idAsBytesSeq */
        $idAsBytesSeq = [];
        foreach (TextUtilForTests::iterateOverChars($binaryId) as $binaryIdCharAsInt) {
            $idAsBytesSeq[] = $binaryIdCharAsInt;
        }

        return IdGenerator::convertBinaryIdToString($idAsBytesSeq);
    }

    private static function convertTimeUnixNano(string|int $protoVal): float
    {
        if (is_int($protoVal)) {
            return floatval($protoVal);
        }

        Assert::assertNotFalse(filter_var($protoVal, FILTER_VALIDATE_INT));
        return floatval($protoVal);
    }

    public function hasRemoteParent(): ?bool
    {
        if (($this->flags & OTelProtoSpanFlags::SPAN_FLAGS_CONTEXT_HAS_IS_REMOTE_MASK) === 0) {
            return null;
        }
        return ($this->flags & OTelProtoSpanFlags::SPAN_FLAGS_CONTEXT_IS_REMOTE_MASK) !== 0;
    }

    private const SPAN_FLAGS_MASKS_TO_NAME = [
        OTelProtoSpanFlags::SPAN_FLAGS_CONTEXT_HAS_IS_REMOTE_MASK => 'HAS_IS_REMOTE',
        OTelProtoSpanFlags::SPAN_FLAGS_CONTEXT_IS_REMOTE_MASK     => 'IS_REMOTE',
    ];

    public function toLog(LogStreamInterface $stream): void
    {
        $flagsToLog = strval($this->flags);
        $flagsHumanReadable = IterableUtil::convertToString(FlagsUtil::extractBitNames($this->flags, self::SPAN_FLAGS_MASKS_TO_NAME), separator: ' | ');
        if (!TextUtil::isEmptyString($flagsHumanReadable)) {
            $flagsToLog .= ' (' . $flagsHumanReadable . ')';
        }
        $customToLog = ['flags' => $flagsToLog];
        $this->toLogLoggableTraitImpl($stream, $customToLog);
    }
}
