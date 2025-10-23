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

/**
 * @noinspection PhpDeprecationInspection
 * Google\Protobuf\Internal\RepeatedField is deprecated, and Google\Protobuf\RepeatedField is used instead.
 */

declare(strict_types=1);

namespace ElasticOTelTests\ComponentTests\Util\OtlpData;

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use Google\Protobuf\RepeatedField as ProtobufRepeatedField;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DeserializationUtil
{
    use StaticClassTrait;

    /**
     * @template TSourceElementType
     * @template TResultElementType
     *
     * @param ProtobufRepeatedField<mixed> $source
     * @param callable(TSourceElementType): ?TResultElementType $deserializeElement
     *
     * @return TResultElementType[]
     */
    public static function deserializeArrayFromOTelProto(ProtobufRepeatedField $source, callable $deserializeElement): array
    {
        $logCtx = compact('source');
        $logCtx['source count'] = count($source);
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext($logCtx);
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        DebugContext::getCurrentScope(/* out */ $dbgCtx, $logCtx);

        $result = [];
        foreach ($source as $sourceElement) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, '', compact('sourceElement'));
            if (($resultElement = $deserializeElement($sourceElement)) === null) {
                continue;
            }
            $result[] = $resultElement;
        }
        return $result;
    }

    /**
     * @template TSource
     * @template TResult
     *
     * @param ?TSource $source
     * @param callable(TSource): TResult $deserialize
     *
     * @phpstan-return ?TResult
     */
    public static function deserializeNullableFromOTelProto(mixed $source, callable $deserialize): mixed
    {
        if ($source === null) {
            return null;
        }

        return $deserialize($source);
    }
}
