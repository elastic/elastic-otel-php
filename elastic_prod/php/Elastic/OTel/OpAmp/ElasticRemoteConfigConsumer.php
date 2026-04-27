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

namespace Elastic\OTel\OpAmp;

use OpenTelemetry\Distro\RemoteConfigConsumerInterface;

/**
 * Elastic OpAMP remote config consumer.
 *
 * Looks for the 'elastic' key in the OpAMP file map, decodes it as JSON,
 * and delegates to ElasticRemoteConfigParser.
 *
 * @internal
 */
final class ElasticRemoteConfigConsumer implements RemoteConfigConsumerInterface
{
    private const REMOTE_CONFIG_FILE_NAME = 'elastic';

    /**
     * @param array<string, string> $fileNameToContent
     */
    public function applyRemoteConfig(array $fileNameToContent): void
    {
        if (!array_key_exists(self::REMOTE_CONFIG_FILE_NAME, $fileNameToContent)) {
            return;
        }

        $content = $fileNameToContent[self::REMOTE_CONFIG_FILE_NAME];
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            error_log('[EDOT] [ERROR] Failed to decode remote config JSON for key "' . self::REMOTE_CONFIG_FILE_NAME . '": ' . json_last_error_msg());
            return;
        }

        ElasticRemoteConfigParser::parseAndApply($decoded);
    }
}