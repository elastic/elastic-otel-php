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

namespace ElasticOTelTests\Util\Log;

use Elastic\OTel\Util\SingletonInstanceTrait;
use ElasticOTelTests\Util\LimitedSizeCache;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @phpstan-type ConverterToLog callable(object): mixed
 * @phpstan-type FinderConverterToLog callable(object): ?ConverterToLog
 */
final class LogExternalClassesRegistry
{
    use SingletonInstanceTrait;

    /** @var FinderConverterToLog[] */
    private array $findersConverterToLog = [];

    /** @var LimitedSizeCache<class-string<object>, ?ConverterToLog> */
    private LimitedSizeCache $classNameToConverterCache;

    private const CACHE_COUNT_LOW_WATER_MARK = 10000;
    private const CACHE_COUNT_HIGH_WATER_MARK = 2 * self::CACHE_COUNT_LOW_WATER_MARK;

    private function __construct()
    {
        $this->classNameToConverterCache = new LimitedSizeCache(countLowWaterMark: self::CACHE_COUNT_LOW_WATER_MARK, countHighWaterMark: self::CACHE_COUNT_HIGH_WATER_MARK);
    }

    /**
     * @param FinderConverterToLog $finderConverterToLog
     */
    public function addFinder(callable $finderConverterToLog): void
    {
        self::singletonInstance()->findersConverterToLog[] = $finderConverterToLog;
    }

    /**
     * @param object $object
     *
     * @return ?ConverterToLog
     */
    public function finderConverterToLog(object $object): ?callable
    {
        /**
         * @return ?ConverterToLog
         */
        $queryFinders = function () use ($object): ?callable {
            foreach ($this->findersConverterToLog as $finder) {
                if (($converter = $finder($object)) !== null) {
                    return $converter;
                }
            }
            return null;
        };

        return $this->classNameToConverterCache->getIfCachedElseCompute(get_class($object), $queryFinders);
    }
}
