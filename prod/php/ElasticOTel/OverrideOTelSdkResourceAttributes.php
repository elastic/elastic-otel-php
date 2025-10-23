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

namespace Elastic\OTel;

use Elastic\OTel\Util\StaticClassTrait;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OverrideOTelSdkResourceAttributes
{
    use StaticClassTrait;

    public const INSTRUMENTED_CLASS_NAME = "OpenTelemetry\\SDK\\Resource\\ResourceInfoFactory";
    public const INSTRUMENTED_METHOD_NAME = 'defaultResource';

    public static function registerHook(string $elasticOTelNativePartVersion): void
    {
        $elasticOTelVersion = self::buildElasticOTelVersion($elasticOTelNativePartVersion);
        BootstrapStageLogger::logDebug(
            'Registering post hook for ' . self::INSTRUMENTED_CLASS_NAME . '::' . self::INSTRUMENTED_METHOD_NAME .
            '; elasticOTelVersion: ' . $elasticOTelVersion . ' ...',
            __FILE__,
            __LINE__,
            __CLASS__,
            __FUNCTION__,
        );

        InstrumentationBridge::singletonInstance()->hook(
            class: self::INSTRUMENTED_CLASS_NAME,
            function: self::INSTRUMENTED_METHOD_NAME,
            post: static function ($thisObj, array $args, mixed $retVal) use ($elasticOTelVersion): mixed {
                BootstrapStageLogger::logDebug(
                    'Entered post hook for ' . self::INSTRUMENTED_CLASS_NAME . '::' . self::INSTRUMENTED_METHOD_NAME .
                    '; elasticOTelVersion: ' . $elasticOTelVersion . '; retVal type: ' . get_debug_type($retVal),
                    __FILE__,
                    __LINE__,
                    __CLASS__,
                    __FUNCTION__,
                );

                if (!($retVal instanceof ResourceInfo)) {
                    BootstrapStageLogger::logError(
                        'Intercepted call return value has unexpected type: ' . get_debug_type($retVal) .
                        ' (expected ' . ResourceInfo::class . ') - exiting without overriding',
                        __FILE__,
                        __LINE__,
                        __CLASS__,
                        __FUNCTION__,
                    );
                    return $retVal;
                }
                /** @var ResourceInfo $retVal */

                return $retVal->merge(self::buildOverridingResourceInfo($retVal, $elasticOTelVersion));
            }
        );
    }

    private static function buildElasticOTelVersion(string $nativePartVersion): string
    {
        if ($nativePartVersion === PhpPartVersion::VALUE) {
            return $nativePartVersion;
        }

        BootstrapStageLogger::logWarning(
            'Native part and PHP part versions do not match. native part version: ' . $nativePartVersion . '; PHP part version: ' . PhpPartVersion::VALUE,
            __FILE__,
            __LINE__,
            __CLASS__,
            __FUNCTION__
        );
        return $nativePartVersion . '/' . PhpPartVersion::VALUE;
    }

    private static function buildOverridingResourceInfo(ResourceInfo $base, string $elasticOTelVersion): ResourceInfo
    {
        /**
         * @see https://github.com/elastic/apm/blob/9a8390a161db1cab0f7e27f03111ff4bececf523/specs/agents/otel-distribution.md?plain=1#L79
         * @see https://github.com/elastic/opentelemetry-lib/blob/434982a9d78a9b0ee1f47bccb9f03d6b7bf3570f/enrichments/internal/elastic/resource.go#L102
         * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts
         *
         * - `telemetry.distro.name`: must be set to `elastic`
         * - `telemetry.distro.version`: must reflect the distribution version
         * - `agent.name`: is built by Elastic's ingestion as <telemetry.sdk.name>/<telemetry.sdk.language>/<telemetry.distro.name> and expected by Kibana to be `opentelemetry/php/elastic`
         */

        $attributes = [
            ResourceAttributes::TELEMETRY_DISTRO_NAME => 'elastic',
            ResourceAttributes::TELEMETRY_DISTRO_VERSION => $elasticOTelVersion,
        ];
        return ResourceInfo::create(Attributes::create($attributes), $base->getSchemaUrl());
    }
}
