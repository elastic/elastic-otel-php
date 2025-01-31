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

use Closure;
use ElasticOTelTests\Util\Config\RawSnapshotFromArray;
use ElasticOTelTests\Util\Config\RawSnapshotInterface;
use ElasticOTelTests\Util\Config\RawSnapshotSourceInterface;
use Override;

final class RequestHeadersRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const HEADER_NAMES_PREFIX = 'ELASTIC_OTEL_PHP_TESTS_';

    /** @var Closure(string): ?string */
    private Closure $getHeaderValue;

    /**
     * @param Closure $getHeaderValue
     *
     * @phpstan-param Closure(string): ?string  $getHeaderValue
     */
    public function __construct(Closure $getHeaderValue)
    {
        $this->getHeaderValue = $getHeaderValue;
    }

    public static function optionNameToHeaderName(string $optName): string
    {
        return self::HEADER_NAMES_PREFIX . strtoupper($optName);
    }

    /** @inheritDoc */
    #[Override]
    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> $optionNameToHeaderValue */
        $optionNameToHeaderValue = [];

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $headerValue = ($this->getHeaderValue)(self::optionNameToHeaderName($optionName));
            if ($headerValue !== null) {
                $optionNameToHeaderValue[$optionName] = $headerValue;
            }
        }

        return new RawSnapshotFromArray($optionNameToHeaderValue);
    }
}
