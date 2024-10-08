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

namespace Elastic\OTel\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnvVarsRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const DEFAULT_NAME_PREFIX = 'ELASTIC_OTEL_';

    /** @var string */
    private $envVarNamesPrefix;

    /**
     * @param string $envVarNamesPrefix
     */
    public function __construct(string $envVarNamesPrefix)
    {
        $this->envVarNamesPrefix = $envVarNamesPrefix;
    }

    public static function optionNameToEnvVarName(string $envVarNamesPrefix, string $optionName): string
    {
        return $envVarNamesPrefix . strtoupper($optionName);
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optionNameToEnvVarValue = [];

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $envVarValue = getenv(self::optionNameToEnvVarName($this->envVarNamesPrefix, $optionName));
            if ($envVarValue !== false) {
                $optionNameToEnvVarValue[$optionName] = $envVarValue;
            }
        }

        return new RawSnapshotFromArray($optionNameToEnvVarValue);
    }
}