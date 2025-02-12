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

namespace ElasticOTelTests\Util;

use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\Log\LogExternalClassesRegistry;
use PHPUnit\Event\Code\Test as PHPUnitEventCodeTest;
use PHPUnit\Event\Code\TestMethod as PHPUnitEventCodeTestMethod;
use PHPUnit\Event\Event as PHPUnitEvent;
use PHPUnit\Event\Telemetry\Info as PHPUnitTelemetryInfo;
use PHPUnit\Event\Telemetry\HRTime as PHPUnitTelemetryHRTime;

/**
 * @phpstan-import-type ConverterToLog from LogExternalClassesRegistry
 */
final class PHPUnitToLogConverters
{
    use StaticClassTrait;

    public static function register(): void
    {
        LogExternalClassesRegistry::singletonInstance()->addFinder(self::findConverter(...));
    }

    /**
     * @return ?ConverterToLog
     */
    public static function findConverter(object $object): ?callable
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $result = match (true) {
            $object instanceof PHPUnitEvent => self::convertPHPUnitEvent(...),
            $object instanceof PHPUnitEventCodeTestMethod => self::convertPHPUnitEventCodeTestMethod(...),
            $object instanceof PHPUnitEventCodeTest => self::convertPHPUnitEventCodeTest(...),
            $object instanceof PHPUnitTelemetryHRTime => self::convertPHPUnitTelemetryHRTime(...),
            $object instanceof PHPUnitTelemetryInfo => self::convertPHPUnitTelemetryInfo(...),
            default => null
        };

        /** @var ?ConverterToLog $result */
        return $result; // @phpstan-ignore varTag.nativeType
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertPHPUnitEvent(PHPUnitEvent $object): array
    {
        return ['asString' => $object->asString(), 'telemetryInfo' => $object->telemetryInfo()];
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertPHPUnitEventCodeTestMethod(PHPUnitEventCodeTestMethod $object): array
    {
        $result = ['class::method' => ($object->className() . '::' . $object->methodName())];
        if ($object->testData()->hasDataFromDataProvider()) {
            $dataSetName = $object->testData()->dataFromDataProvider()->dataSetName();
            $dataSetDesc = is_int($dataSetName) ? "#$dataSetName" : $dataSetName;
            $result['data set'] = $dataSetDesc;
        }
        return $result;
    }

    private static function convertPHPUnitEventCodeTest(PHPUnitEventCodeTest $object): string
    {
        return $object->id();
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertPHPUnitTelemetryHRTime(PHPUnitTelemetryHRTime $object): array
    {
        return ['seconds' => $object->seconds(), 'nanoseconds' => $object->nanoseconds()];
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertPHPUnitTelemetryInfo(PHPUnitTelemetryInfo $object): array
    {
        return [
            'time' => $object->time(),
            'durationSincePrevious' => $object->durationSincePrevious()->asString(),
            'durationSinceStart' => $object->durationSinceStart()->asString(),
            'memoryUsage (bytes)' => $object->memoryUsage()->bytes(),
            'memoryUsageSincePrevious (bytes)' => $object->memoryUsageSincePrevious()->bytes(),
        ];
    }
}
