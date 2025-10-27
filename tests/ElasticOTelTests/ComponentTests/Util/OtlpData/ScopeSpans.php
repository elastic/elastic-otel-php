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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use Opentelemetry\Proto\Trace\V1\ScopeSpans as OTelProtoScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span as OTelProtoSpan;

/**
 * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.8.0/opentelemetry/proto/trace/v1/trace.proto#L68
 */
class ScopeSpans
{
    /**
     * @param Span[] $spans
     */
    public function __construct(
        public readonly ?InstrumentationScope $scope,
        public readonly array $spans,
        public readonly string $schemaUrl,
    ) {
    }

    public static function deserializeFromOTelProto(OTelProtoScopeSpans $source): self
    {
        return new self(
            scope: DeserializationUtil::deserializeNullableFromOTelProto($source->getScope(), InstrumentationScope::deserializeFromOTelProto(...)),
            spans: DeserializationUtil::deserializeArrayFromOTelProto($source->getSpans(), self::deserializeSpanFromOTelProto(...)),
            schemaUrl: $source->getSchemaUrl(),
        );
    }

    private static function deserializeSpanFromOTelProto(OTelProtoSpan $source): ?Span
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('source'));
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $dbgCtx->add(compact('source'));

        $span = Span::deserializeFromOTelProto($source);
        if (($reason = Span::reasonToDiscard($span)) !== null) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Span discarded', compact('reason', 'span'));
            return null;
        }

        return $span;
    }
}
