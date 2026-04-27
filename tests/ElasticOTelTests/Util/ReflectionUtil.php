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
use PHPUnit\Framework\Assert;
use ReflectionClass;

final class ReflectionUtil
{
    use StaticClassTrait;

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function getConstValue(string $class, string $constName): mixed
    {
        $reflClass = new ReflectionClass($class);
        Assert::assertTrue($reflClass->hasConstant($constName));
        return $reflClass->getConstant($constName);
    }

    public static function getPropertyValue(object $obj, string $propertyName): mixed
    {
        $reflClass = new ReflectionClass($obj);
        Assert::assertTrue($reflClass->hasProperty($propertyName));
        return $reflClass->getProperty($propertyName)->getValue($obj);
    }
}
