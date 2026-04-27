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

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\ComponentTests\Util\IdGenerator;
use ElasticOTelTests\ComponentTests\Util\TestInfraHttpServerProcessBase;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\TextUtilForTests;
use Opentelemetry\Proto\Trace\V1\Span as ProtoSpan;
use Opentelemetry\Proto\Trace\V1\SpanFlags as ProtoSpanFlags;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Assert;

final class Span implements LoggableInterface
{
    use LoggableTrait;

    /**
     * @param non-negative-int $droppedAttributesCount
     */
    public function __construct(
        public readonly Attributes $attributes,
        public readonly int $droppedAttributesCount,
        public readonly string $id,
        public readonly SpanKind $kind,
        public readonly string $name,
        public readonly ?string $parentId,
        public readonly string $traceId,
        public readonly FlagsBase $flags,
        public readonly float $startTimeUnixNano,
        public readonly float $endTimeUnixNano,
    ) {
    }

    public static function fromProto(ProtoSpan $proto): self
    {
        return new self(
            attributes: Attributes::fromProto($proto->getAttributes()),
            droppedAttributesCount: AssertEx::isNonNegativeInt($proto->getDroppedAttributesCount()),
            id: self::convertId($proto->getSpanId()),
            kind: SpanKind::fromProto($proto->getKind()),
            name: $proto->getName(),
            parentId: self::convertNullableId($proto->getParentSpanId()),
            traceId: self::convertId($proto->getTraceId()),
            flags: new SpanFlags($proto->getFlags()),
            startTimeUnixNano: self::convertTimeUnixNano($proto->getStartTimeUnixNano()),
            endTimeUnixNano: self::convertTimeUnixNano($proto->getEndTimeUnixNano()),
        );
    }

    public static function reasonToDiscard(Span $span): ?string
    {
        /** @var string[] $attributesToCheckForTestsInfraUrlSubPath */
        static $attributesToCheckForTestsInfraUrlSubPath = [TraceAttributes::URL_PATH, TraceAttributes::URL_FULL, TraceAttributes::URL_ORIGINAL];
        foreach ($attributesToCheckForTestsInfraUrlSubPath as $attributeName) {
            if (($reason = self::reasonToDiscardIfOptionalAttributeContainsString($span->attributes, $attributeName, TestInfraHttpServerProcessBase::BASE_URI_PATH)) !== null) {
                return $reason;
            }
        }

        return null;
    }

    private static function reasonToDiscardIfOptionalAttributeContainsString(Attributes $attributes, string $attributeName, string $subString): ?string
    {
        if (
            (($attributeValue = $attributes->tryToGetString($attributeName)) !== null)
            &&
            str_contains($attributeValue, $subString)
        ) {
            return 'Attribute (key: `' . $attributeName . '\', value: `' . $attributeValue . '\') contains `' . $subString . '\'';
        }

        return null;
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
        if (!$this->flags->isOn(ProtoSpanFlags::SPAN_FLAGS_CONTEXT_HAS_IS_REMOTE_MASK)) {
            return null;
        }
        return $this->flags->isOn(ProtoSpanFlags::SPAN_FLAGS_CONTEXT_IS_REMOTE_MASK);
    }
}
