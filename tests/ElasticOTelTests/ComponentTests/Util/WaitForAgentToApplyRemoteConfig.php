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

use ElasticOTelTests\ComponentTests\Util\OpampData\AgentRemoteConfig;
use ElasticOTelTests\ComponentTests\Util\OpampData\AgentToServer;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\EnabledLoggerProxyNoLine;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;

final class WaitForAgentToApplyRemoteConfig implements IsEnoughAgentBackendCommsInterface, LoggableInterface
{
    use LoggableTrait;

    private ?EnabledLoggerProxyNoLine $logDebug = null;

    public function __construct(
        private readonly string $remoteConfigHash,
    ) {
    }

    public function isEnough(AgentBackendComms $comms): bool
    {
        if ($this->logDebug !== null) {
            $this->logDebug = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)
                ->ifDebugLevelEnabledNoLine(__FUNCTION__);
        }
        if (IterableUtil::getLastValue($comms->opampAgentToServerRequestsData(), /* out */ $lastAgentToServerRequest)) {
            /** @var AgentToServer $lastAgentToServerRequest */
            if (!($wasConfigAppliedByAgent = AgentRemoteConfig::wasHashAppliedByAgent($this->remoteConfigHash, $lastAgentToServerRequest))) {
                $this->logDebug?->log(__LINE__, 'The last OpAMP AgentToServer does NOT show that config was applied', compact('lastAgentToServerRequest'));
            } else {
                $this->logDebug?->log(__LINE__, 'The last OpAMP AgentToServer shows that config was applied', compact('lastAgentToServerRequest'));
            }
            return $wasConfigAppliedByAgent;
        }

        $this->logDebug?->log(__LINE__, 'There is no OpAMP AgentToServer request in the accumulated AgentBackendComms');
        return false;
    }
}
