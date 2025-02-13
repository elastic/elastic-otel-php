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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\StaticClassTrait;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\LimitedSizeCache;
use ReflectionClass;
use ReflectionException;
use Throwable;
use UnitEnum;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToJsonEncodable
{
    use StaticClassTrait;

    public const MAX_DEPTH_IN_PROD_MODE = 10;

    public static int $maxDepth = self::MAX_DEPTH_IN_PROD_MODE;

    private const IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK = 10000;
    private const IS_DTO_OBJECT_CACHE_MAX_COUNT_HIGH_WATER_MARK = 2 * self::IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK;

    private const ELASTIC_NAMESPACE_PREFIXES = ['Elastic\\OTel\\', 'ElasticOTelTests\\'];

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    public static function convertArrayForMaxDepth(array $value, int $depth): array
    {
        return [LogConsts::MAX_DEPTH_REACHED => $depth, LogConsts::TYPE_KEY => get_debug_type($value), LogConsts::ARRAY_COUNT_KEY => count($value)];
    }

    /**
     * @param object $value
     * @param int    $depth
     *
     * @return array<string, mixed>
     */
    public static function convertObjectForMaxDepth(object $value, int $depth): array
    {
        return [LogConsts::MAX_DEPTH_REACHED => $depth, LogConsts::TYPE_KEY => get_debug_type($value)];
    }

    public static function convert(mixed $value, int $depth): mixed
    {
        if ($value === null) {
            return null;
        }

        // Scalar variables are those containing an int, float, string or bool.
        // Types array, object and resource are not scalar.
        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($depth >= self::$maxDepth) {
                return self::convertArrayForMaxDepth($value, $depth);
            }
            return self::convertArray($value, $depth + 1);
        }

        if (is_resource($value)) {
            return self::convertOpenResource($value);
        }

        if (is_object($value)) {
            if ($depth >= self::$maxDepth) {
                return self::convertObjectForMaxDepth($value, $depth);
            }
            return self::convertObject($value, $depth);
        }

        return [LogConsts::TYPE_KEY => get_debug_type($value), LogConsts::VALUE_AS_STRING_KEY => strval($value)]; /** @phpstan-ignore argument.type */
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private static function convertArray(array $array, int $depth): array
    {
        return self::convertArrayImpl($array, array_is_list($array), $depth);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param bool                $isListArray
     * @param int                 $depth
     *
     * @return array<array-key, mixed>
     */
    private static function convertArrayImpl(array $array, bool $isListArray, int $depth): array
    {
        $arrayCount = count($array);
        $smallArrayMaxCount = $isListArray
            ? LogConsts::SMALL_LIST_ARRAY_MAX_COUNT
            : LogConsts::SMALL_MAP_ARRAY_MAX_COUNT;
        if ($arrayCount <= $smallArrayMaxCount) {
            return self::convertSmallArray($array, $isListArray, $depth);
        }

        $result = [LogConsts::TYPE_KEY => LogConsts::LIST_ARRAY_TYPE_VALUE];
        $result[LogConsts::ARRAY_COUNT_KEY] = $arrayCount;

        $halfOfSmallArrayMaxCount = intdiv($smallArrayMaxCount, 2);
        $firstElements = array_slice($array, 0, $halfOfSmallArrayMaxCount);
        $result['0-' . intdiv($smallArrayMaxCount, 2)]
            = self::convertSmallArray($firstElements, $isListArray, $depth);

        $result[($arrayCount - $halfOfSmallArrayMaxCount) . '-' . $arrayCount]
            = self::convertSmallArray(array_slice($array, -$halfOfSmallArrayMaxCount), $isListArray, $depth);

        return $result;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param bool                $isListArray
     * @param int                 $depth
     *
     * @return array<array-key, mixed>
     */
    private static function convertSmallArray(array $array, bool $isListArray, int $depth): array
    {
        return $isListArray ? self::convertSmallListArray($array, $depth) : self::convertSmallMapArray($array, $depth);
    }

    /**
     * @param array<array-key, mixed> $listArray
     *
     * @return array<array-key, mixed>
     */
    private static function convertSmallListArray(array $listArray, int $depth): array
    {
        $result = [];
        foreach ($listArray as $value) {
            $result[] = self::convert($value, $depth);
        }
        return $result;
    }

    /**
     * @param array<array-key, mixed> $mapArrayValue
     *
     * @return array<array-key, mixed>
     */
    private static function convertSmallMapArray(array $mapArrayValue, int $depth): array
    {
        return self::isStringKeysMapArray($mapArrayValue)
            ? self::convertSmallStringKeysMapArray($mapArrayValue, $depth)
            : self::convertSmallMixedKeysMapArray($mapArrayValue, $depth);
    }

    /**
     * @param array<array-key, mixed> $mapArrayValue
     *
     * @return bool
     */
    private static function isStringKeysMapArray(array $mapArrayValue): bool
    {
        foreach ($mapArrayValue as $key => $_) {
            if (!is_string($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<array-key, mixed> $mapArrayValue
     *
     * @return array<array-key, mixed>
     */
    private static function convertSmallStringKeysMapArray(array $mapArrayValue, int $depth): array
    {
        return array_map(function ($value) use ($depth) {
            return self::convert($value, $depth);
        }, $mapArrayValue);
    }

    /**
     * @param array<array-key, mixed> $mapArrayValue
     * @param int                 $depth
     *
     * @return array<array-key, mixed>
     */
    private static function convertSmallMixedKeysMapArray(array $mapArrayValue, int $depth): array
    {
        $result = [];
        foreach ($mapArrayValue as $key => $value) {
            $result[] = ['key' => self::convert($key, $depth), 'value' => self::convert($value, $depth)];
        }
        return $result;
    }

    /**
     * @param resource $resource
     *
     * @return array<string, mixed>
     */
    private static function convertOpenResource($resource): array
    {
        return [
            LogConsts::TYPE_KEY          => LogConsts::RESOURCE_TYPE_VALUE,
            LogConsts::RESOURCE_TYPE_KEY => get_resource_type($resource),
            LogConsts::RESOURCE_ID_KEY   => intval($resource),
        ];
    }

    private static function isFromElasticNamespace(string $fqClassName): bool
    {
        foreach (self::ELASTIC_NAMESPACE_PREFIXES as $prefix) {
            if (TextUtil::isPrefixOf($prefix, $fqClassName)) {
                return true;
            }
        }
        return false;
    }

    private static function convertObject(object $object, int $depth): mixed
    {
        if ($object instanceof LoggableInterface) {
            return self::convertLoggable($object, $depth);
        }

        if ($object instanceof Throwable) {
            return self::convertThrowable($object, $depth);
        }

        if ($object instanceof LogLevel) {
            return strtoupper($object->name);
        }

        if ($object instanceof UnitEnum) {
            return $object::class . '(' . $object->name . ')';
        }

        if (self::isFromElasticNamespace(get_class($object)) && self::isDtoObject($object)) {
            return self::convertDtoObject($object, $depth);
        }

        if (($converterToLog = LogExternalClassesRegistry::singletonInstance()->finderConverterToLog($object)) !== null) {
            return self::convert($converterToLog($object), $depth);
        }

        if (method_exists($object, '__debugInfo')) {
            return [
                LogConsts::TYPE_KEY                => get_class($object),
                LogConsts::VALUE_AS_DEBUG_INFO_KEY => self::convert($object->__debugInfo(), $depth),
            ];
        }

        if (method_exists($object, '__toString')) {
            return [
                LogConsts::TYPE_KEY            => get_class($object),
                LogConsts::VALUE_AS_STRING_KEY => self::convert($object->__toString(), $depth),
            ];
        }

        return [
            LogConsts::TYPE_KEY        => get_class($object),
            LogConsts::OBJECT_ID_KEY   => spl_object_id($object),
            LogConsts::OBJECT_HASH_KEY => spl_object_hash($object),
        ];
    }

    /**
     * @param LoggableInterface $loggable
     * @param int               $depth
     *
     * @return mixed
     */
    private static function convertLoggable(LoggableInterface $loggable, int $depth): mixed
    {
        $logStream = new LogStream();
        $loggable->toLog($logStream);
        return self::convert($logStream->value, $depth);
    }

    /**
     * @param Throwable $throwable
     * @param int       $depth
     *
     * @return array<string, mixed>
     */
    private static function convertThrowable(Throwable $throwable, int $depth): array
    {
        return [
            LogConsts::TYPE_KEY            => get_class($throwable),
            LogConsts::VALUE_AS_STRING_KEY => self::convert($throwable->__toString(), $depth),
        ];
    }

    /**
     * @return string|array<string, mixed>
     *
     * @phpstan-ignore return.unusedType
     */
    private static function convertDtoObject(object $object, int $depth): string|array
    {
        $class = get_class($object);
        try {
            $currentClass = new ReflectionClass($class);
        } catch (ReflectionException $ex) { // @phpstan-ignore catch.neverThrown
            return LoggingSubsystem::onInternalFailure('Failed to reflect', ['class' => $class], $ex);
        }

        $nameToValue = [];
        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $propName = $reflectionProperty->name;
                $propValue = $reflectionProperty->getValue($object);
                $nameToValue[$propName] = self::convert($propValue, $depth);
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }
        return $nameToValue;
    }

    /**
     * @return LimitedSizeCache<class-string<object>, bool>
     */
    private static function isDtoObjectCacheSingleton(): LimitedSizeCache
    {
        /**
         * @var ?LimitedSizeCache<class-string<object>, bool>
         *
         * @noinspection PhpVarTagWithoutVariableNameInspection
         */
        static $isDtoObjectCache = null;

        if ($isDtoObjectCache === null) {
            $isDtoObjectCache = new LimitedSizeCache(
                countLowWaterMark:  self::IS_DTO_OBJECT_CACHE_MAX_COUNT_LOW_WATER_MARK,
                countHighWaterMark: self::IS_DTO_OBJECT_CACHE_MAX_COUNT_HIGH_WATER_MARK
            );
            /** @var LimitedSizeCache<class-string<object>, bool> $isDtoObjectCache */
        }

        return $isDtoObjectCache;
    }

    private static function isDtoObject(object $object): bool
    {
        return self::isDtoObjectCacheSingleton()->getIfCachedElseCompute(get_class($object), self::detectIfDtoObject(...));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return bool
     */
    private static function detectIfDtoObject(string $className): bool
    {
        try {
            $currentClass = new ReflectionClass($className);
        } catch (ReflectionException $ex) { // @phpstan-ignore catch.neverThrown
            LoggingSubsystem::onInternalFailure('Failed to reflect', ['className' => $className], $ex);
            return false;
        }

        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                if (!$reflectionProperty->isPublic()) {
                    return false;
                }
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }

        return true;
    }
}
