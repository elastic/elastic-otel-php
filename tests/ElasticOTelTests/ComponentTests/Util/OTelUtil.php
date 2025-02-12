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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span as OTelApiSpan;
use OpenTelemetry\API\Trace\SpanInterface as OTelApiSpanInterface;
use OpenTelemetry\API\Trace\SpanKind as OTelSpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Version;
use Throwable;

/**
 * @phpstan-type OTelAttributeScalarValue bool|int|float|string|null
 * @phpstan-type OTelAttributeValue OTelAttributeScalarValue|array<OTelAttributeScalarValue>
 * @phpstan-type OTelAttributesMapIterable iterable<non-empty-string, OTelAttributeValue>
 * @phpstan-type IntLimitedToOTelSpanKind OTelSpanKind::KIND_*
 */
class OTelUtil
{
    public static function getTracer(): TracerInterface
    {
        return Globals::tracerProvider()->getTracer(name: 'co.elastic.php.elastic.component-tests', version: Version::VERSION_1_27_0->value);
    }

    /**
     * @param non-empty-string          $spanName
     * @param IntLimitedToOTelSpanKind  $spanKind
     * @param OTelAttributesMapIterable $attributes
     *
     * @noinspection PhpDocSignatureInspection
     */
    public static function startSpan(TracerInterface $tracer, string $spanName, int $spanKind = OTelSpanKind::KIND_INTERNAL, iterable $attributes = []): OTelApiSpanInterface
    {
        $parentCtx = Context::getCurrent();
        $newSpanBuilder = $tracer->spanBuilder($spanName)->setParent($parentCtx)->setSpanKind($spanKind)->setAttributes($attributes);
        $newSpan = $newSpanBuilder->startSpan();
        $newSpanCtx = $newSpan->storeInContext($parentCtx);
        Context::storage()->attach($newSpanCtx);
        return $newSpan;
    }

    /**
     * @param OTelAttributesMapIterable $attributes
     */
    public static function endActiveSpan(?Throwable $throwable = null, ?string $errorStatus = null, iterable $attributes = []): void
    {
        $scope = Context::storage()->scope();
        if ($scope === null) {
            return;
        }
        $scope->detach();
        $span = OTelApiSpan::fromContext($scope->context());

        $span->setAttributes($attributes);

        if ($errorStatus !== null) {
            $span->setAttribute(TraceAttributes::EXCEPTION_MESSAGE, $errorStatus);
            $span->setStatus(StatusCode::STATUS_ERROR, $errorStatus);
        }

        if ($throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
        }

        $span->end();
    }

    /**
     * @param non-empty-string          $spanName
     * @param IntLimitedToOTelSpanKind  $spanKind
     * @param OTelAttributesMapIterable $attributes
     *
     * @noinspection PhpDocSignatureInspection
     */
    public static function startEndSpan(
        TracerInterface $tracer,
        string $spanName,
        int $spanKind = OTelSpanKind::KIND_INTERNAL,
        iterable $attributes = [],
        ?Throwable $throwable = null,
        ?string $errorStatus = null
    ): void {
        self::startSpan($tracer, $spanName, $spanKind, $attributes);
        self::endActiveSpan($throwable, $errorStatus);
    }

    /**
     * @param iterable<string> $attributeKeys
     *
     * @return array<string, mixed>
     */
    public static function dbgDescForSpan(OTelApiSpanInterface $span, iterable $attributeKeys): array
    {
        $result = ['class' => get_class($span), 'isRecording' => $span->isRecording()];
        if (method_exists($span, 'getName')) {
            $result['name'] = $span->getName();
        }
        if (method_exists($span, 'getAttribute')) {
            $attributes = [];
            foreach ($attributeKeys as $attributeKey) {
                $attributes[$attributeKey] = $span->getAttribute($attributeKey);
            }
            $result['attributes'] = $attributes;
        }
        return $result;
    }

    /**
     * @param OTelAttributesMapIterable $attributes
     */
    public static function setActiveSpanAttributes(iterable $attributes): void
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $logger->addAllContext(compact('attributes'));

        $currentCtx = Context::getCurrent();
        $span = OTelApiSpan::fromContext($currentCtx);

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Before setting attributes', ['span' => self::dbgDescForSpan($span, IterableUtil::keys($attributes))]);
        $span->setAttributes($attributes);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'After setting attributes', ['span' => self::dbgDescForSpan($span, IterableUtil::keys($attributes))]);
    }
}
