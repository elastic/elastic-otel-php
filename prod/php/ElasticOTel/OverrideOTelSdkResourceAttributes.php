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

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OverrideOTelSdkResourceAttributes implements ResourceDetectorInterface
{
    private static ?string $distroVersion = null;

    public static function register(string $elasticOTelNativePartVersion): void
    {
        self::$distroVersion = self::buildDistroVersion($elasticOTelNativePartVersion);
        Registry::registerResourceDetector(self::class, new self());
        BootstrapStageLogger::logDebug('Registered; distroVersion: ' . self::$distroVersion, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
    }

    public function getResource(): ResourceInfo
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
            ResourceAttributes::TELEMETRY_DISTRO_VERSION => self::getDistroVersion(),
        ];

        BootstrapStageLogger::logDebug('Returning attributes: ' . json_encode($attributes), __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }

    private static function buildDistroVersion(string $elasticOTelNativePartVersion): string
    {
        if ($elasticOTelNativePartVersion === PhpPartVersion::VALUE) {
            return $elasticOTelNativePartVersion;
        }

        $logMsg = 'Native part and PHP part versions do not match. native part version: ' . $elasticOTelNativePartVersion . '; PHP part version: ' . PhpPartVersion::VALUE;
        BootstrapStageLogger::logWarning($logMsg, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        return $elasticOTelNativePartVersion . '/' . PhpPartVersion::VALUE;
    }

    public static function getDistroVersion(): string
    {
        return self::$distroVersion ?? PhpPartVersion::VALUE;
    }
}
