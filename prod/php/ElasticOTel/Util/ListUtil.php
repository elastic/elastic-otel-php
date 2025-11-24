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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel\Util;

final class ListUtil
{
    use StaticClassTrait;

    /**
     * @template T
     *
     * @param list<T> $from
     * @param list<T> $to
     */
    public static function append(array $from, /* in,out */ array &$to): void
    {
        $to = array_merge($to, $from);
    }

    /**
     * @template T
     *
     * @param list<T> $list1
     * @param list<T> $list2
     * @param list<T> ...$moreLists
     *
     * @return list<T>
     */
    public static function concat(array $list1, array $list2, array ...$moreLists): array
    {
        $result = [];
        self::append($list1, /* ref */ $result);
        self::append($list2, /* ref */ $result);
        foreach ($moreLists as $listToAppend) {
            self::append($listToAppend, /* ref */ $result);
        }
        return $result;
    }
}
