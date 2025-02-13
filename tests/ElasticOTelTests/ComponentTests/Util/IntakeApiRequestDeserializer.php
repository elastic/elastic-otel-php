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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\HttpContentTypes;
use ElasticOTelTests\Util\HttpHeaderNames;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use Google\Protobuf\Internal\RepeatedField;
use OpenTelemetry\Contrib\Otlp\ProtobufSerializer;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceRequest;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span as OTelProtoSpan;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Assert;

final class IntakeApiRequestDeserializer
{
    public static function deserialize(IntakeApiRequest $intakeApiRequest): ParsedExportedData
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('intakeApiRequest'));
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Processing intake API request');

        $body = base64_decode($intakeApiRequest->bodyBase64Encoded);
        Assert::assertIsString($body); // @phpstan-ignore staticMethod.alreadyNarrowedType
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Body size: ' . strlen($body));

        $contentLength = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_LENGTH, $intakeApiRequest->headers);
        AssertEx::stringSameAsInt(strlen($body), $contentLength);

        $contentType = HttpClientUtilForTests::getSingleHeaderValue(HttpHeaderNames::CONTENT_TYPE, $intakeApiRequest->headers);
        Assert::assertSame(HttpContentTypes::PROTOBUF, $contentType);

        $serializer = ProtobufSerializer::getDefault();
        $exportTraceServiceRequest = new ExportTraceServiceRequest();
        $serializer->hydrate($exportTraceServiceRequest, $body);

        return new ParsedExportedData(self::deserializeSpans($exportTraceServiceRequest, $logger));
    }

    /**
     * @param array<string, RepeatedField> $repeatedFieldNameToObj
     *
     * @return array<string, int>
     */
    private static function buildLogContextForRepeatedField(array $repeatedFieldNameToObj): array
    {
        $repeatedFieldName = array_key_first($repeatedFieldNameToObj);
        return ['count(' . $repeatedFieldName . ')' => count($repeatedFieldNameToObj[$repeatedFieldName])];
    }

    /**
     * @return Span[]
     */
    private static function deserializeSpans(ExportTraceServiceRequest $exportTraceServiceRequest, Logger $logger): array
    {
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        $resourceSpansRepeatedField = $exportTraceServiceRequest->getResourceSpans();
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', self::buildLogContextForRepeatedField(compact('resourceSpansRepeatedField')));

        $result = [];
        foreach ($resourceSpansRepeatedField as $resourceSpans) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', compact('resourceSpans'));
            Assert::assertInstanceOf(ResourceSpans::class, $resourceSpans);
            $scopeSpansRepeatedField = $resourceSpans->getScopeSpans();
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', self::buildLogContextForRepeatedField(compact('scopeSpansRepeatedField')));
            foreach ($scopeSpansRepeatedField as $scopeSpans) {
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', compact('scopeSpans'));
                Assert::assertInstanceOf(ScopeSpans::class, $scopeSpans);
                $spansRepeatedField = $scopeSpans->getSpans();
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', self::buildLogContextForRepeatedField(compact('spansRepeatedField')));
                foreach ($spansRepeatedField as $otelProtoSpan) {
                    $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', compact('otelProtoSpan'));
                    Assert::assertInstanceOf(OTelProtoSpan::class, $otelProtoSpan);
                    $span = new Span($otelProtoSpan);
                    if (($reason = self::reasonToDiscard($span)) !== null) {
                        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Span discarded', compact('reason', 'span'));
                        continue;
                    }
                    $result[] = $span;
                }
            }
        }
        return $result;
    }

    private static function reasonToDiscard(Span $span): ?string
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

    /** @noinspection PhpSameParameterValueInspection */
    private static function reasonToDiscardIfOptionalAttributeContainsString(SpanAttributes $attributes, string $attributeName, string $subString): ?string
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
}
