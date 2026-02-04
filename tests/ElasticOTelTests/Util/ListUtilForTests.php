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

final class ListUtilForTests
{
    use StaticClassTrait;

    /**
     * @template T
     *
     * @param T $val
     * @param list<T> $list
     *
     * @return non-empty-list<T>
     */
    public static function bringToFront(mixed $val, array $list): array
    {
        $valIndex = AssertEx::isInt(array_search($val, $list, /* strict: */ true));
        $partBeforeVal = $valIndex === 0 ? [] : array_slice($list, 0, $valIndex);
        $partAfterVal = $valIndex === (count($list) - 1) ? [] : array_slice($list, $valIndex + 1);
        return array_merge([$val], $partBeforeVal, $partAfterVal);
    }
}
