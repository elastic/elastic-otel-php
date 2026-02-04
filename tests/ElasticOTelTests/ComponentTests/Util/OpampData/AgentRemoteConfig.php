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

namespace ElasticOTelTests\ComponentTests\Util\OpampData;

use GeneratedForElasticOTelTests\OpampProto\AgentRemoteConfig as ProtoAgentRemoteConfig;
use GeneratedForElasticOTelTests\OpampProto\RemoteConfigStatuses as ProtoRemoteConfigStatuses;

/**
 * @see https://github.com/open-telemetry/opamp-spec/blob/v0.14.0/proto/opamp.proto#L1013
 */
final class AgentRemoteConfig
{
    public function __construct(
        public readonly AgentConfigMap $config,
        public readonly string $configHash,
    ) {
    }

    public function wasAlreadySentToAgent(AgentToServer $agentToServer): bool
    {
        return self::wasHashAlreadySentToAgent($this->configHash, $agentToServer);
    }

    private static function wasHashAlreadySentToAgent(string $configHash, AgentToServer $agentToServer): bool
    {
        return $agentToServer->remoteConfigStatus?->lastRemoteConfigHash === $configHash;
    }

    public static function wasHashAppliedByAgent(string $configHash, AgentToServer $agentToServer): bool
    {
        return self::wasHashAlreadySentToAgent($configHash, $agentToServer) && $agentToServer->remoteConfigStatus?->status === ProtoRemoteConfigStatuses::RemoteConfigStatuses_APPLIED;
    }

    public function toProto(): ProtoAgentRemoteConfig
    {
        $result = new ProtoAgentRemoteConfig();
        $result->setConfig($this->config->toProto());
        $result->setConfigHash($this->configHash);
        return $result;
    }
}
