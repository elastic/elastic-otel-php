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

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-type MemoryUsage array{'desc': string, 'real': int, 'including_unused': int}
 */
final class MemoryUtil
{
    use StaticClassTrait;

    public static function formatSizeInBytes(int $sizeInBytes): string
    {
        /** @var list<string> $unitsSuffixes */
        static $unitsSuffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        /**
         * @var int $unitsStep
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        static $unitsStep = 1024;

        $absSizeInBytes = AssertEx::isInt(abs($sizeInBytes));
        $sign = $sizeInBytes >= 0 ? 1 : -1;

        $powOf2 = intval(AssertEx::isFloat(floor(($absSizeInBytes ? log($absSizeInBytes) : 0) / log($unitsStep))));
        $unitsIndex = min($powOf2, count($unitsSuffixes) - 1);
        $sizeInUnits = $absSizeInBytes / pow($unitsStep, $unitsIndex);

        $precision = $sizeInUnits >= 100 ? 0 : ($sizeInUnits >= 10 ? 1 : 2);
        return $sign * round($sizeInUnits, $precision) . ' ' . $unitsSuffixes[$unitsIndex];
    }

    /**
     * @return MemoryUsage
     */
    public static function getMemoryUsage(string $dbgCurrentStateDesc): array
    {
        return ['desc' => $dbgCurrentStateDesc, 'real' => memory_get_usage(/* real_usage: */ true), 'including_unused' => memory_get_usage()];
    }

    /**
     * @param array<string, array<int|string>> $nameToArray
     *
     * @return array<string, array<string>>
     */
    private static function formatArrayNameValuesSizeInBytes(array $nameToArray): array
    {
        Assert::assertCount(1, $nameToArray);
        $name = array_key_first($nameToArray);
        $formattedArray = array_map(fn($value) => is_int($value) ? self::formatSizeInBytes($value) : $value, AssertEx::isArray($nameToArray[$name]));
        /** @var array<string, string> $formattedArray */
        return [$name => $formattedArray];
    }

    /**
     * @param string $dbgCurrentStateDesc
     * @param ?MemoryUsage $prev
     *
     * @return MemoryUsage
     */
    public static function logMemoryUsage(string $dbgCurrentStateDesc, ?array $prev = null): array
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);

        $current = self::getMemoryUsage($dbgCurrentStateDesc);
        $ctx = self::formatArrayNameValuesSizeInBytes(compact('current'));
        if ($prev !== null) {
            $delta = [];
            foreach ($current as $key => $value) {
                if ($key === 'desc') {
                    /** @var string $value */
                    $delta[$key] = $value . '-' . $prev[$key];
                } else {
                    /** @var int $value */
                    $delta[$key] = $value - $prev[$key];
                }
            }
            $ctx = array_merge(self::formatArrayNameValuesSizeInBytes(compact('delta')), $ctx);
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log("Memory message", $ctx);

        return $current;
    }
}
