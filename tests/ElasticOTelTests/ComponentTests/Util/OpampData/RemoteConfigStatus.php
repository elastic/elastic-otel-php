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

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Exception;
use GeneratedForElasticOTelTests\OpampProto\RemoteConfigStatus as ProtoRemoteConfigStatus;
use GeneratedForElasticOTelTests\OpampProto\RemoteConfigStatuses as ProtoRemoteConfigStatuses;
use Override;

/**
 * @see https://github.com/open-telemetry/opamp-spec/blob/v0.14.0/proto/opamp.proto#L820
 */
final class RemoteConfigStatus implements LoggableInterface
{
    use LoggableTrait;

    public function __construct(
        public readonly string $lastRemoteConfigHash,
        public readonly int $status,
        public readonly string $errorMessage,
    ) {
    }

    public static function deserializeFromProto(ProtoRemoteConfigStatus $proto): self
    {
        return new self(
            lastRemoteConfigHash: $proto->getLastRemoteConfigHash(),
            status: $proto->getStatus(),
            errorMessage: $proto->getErrorMessage(),
        );
    }

    #[Override]
    public function toLog(LogStreamInterface $stream): void
    {
        try {
            $statusAsString = AssertEx::isString(ProtoRemoteConfigStatuses::name($this->status));
        } catch (Exception $exception) {
            $statusAsString = $exception->getMessage();
        }
        $customToLog = ['status' => "$this->status ($statusAsString)"];
        $this->toLogLoggableTraitImpl($stream, $customToLog);
    }
}
