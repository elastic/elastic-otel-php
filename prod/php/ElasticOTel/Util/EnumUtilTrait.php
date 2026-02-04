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

trait EnumUtilTrait
{
    public static function tryToFindByName(string $enumName, bool $isCaseSensitive = false): ?self
    {
        /** @var ?array<string, self> $nameToSelf */
        static $nameToSelf = null;
        /** @var ?array<string, self> $lowerCaseNameToSelf */
        static $lowerCaseNameToSelf = null;

        if ($nameToSelf === null) {
            $nameToSelf = [];
            $lowerCaseNameToSelf = [];
            foreach (self::cases() as $enumCase) {
                $nameToSelf[$enumCase->name] = $enumCase;
                $lowerCaseNameToSelf[strtolower($enumCase->name)] = $enumCase;
            }
        }
        /** @var array<string, self> $nameToSelf */
        /** @var array<string, self> $lowerCaseNameToSelf */

        return ArrayUtil::getValueIfKeyExistsElse($isCaseSensitive ? $enumName : strtolower($enumName), $isCaseSensitive ? $nameToSelf : $lowerCaseNameToSelf, null);
    }

    /**
     * @return list<string>
     */
    public static function casesNames(): array
    {
        /** @var ?list<string> $result */
        static $result = null;
        if ($result === null) {
            $result = array_map(fn($enumCase) => $enumCase->name, self::cases());
        }
        /** @var list<string> $result */

        return $result;
    }
}
