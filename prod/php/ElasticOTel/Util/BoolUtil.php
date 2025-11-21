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

namespace Elastic\OTel\Util;

final class BoolUtil
{
    use StaticClassTrait;

    public static function toString(bool $val): string
    {
        return $val ? 'true' : 'false';
    }

    public static function parseValue(string $envVarVal): ?bool
    {
        foreach (['true', 'yes', 'on', '1'] as $trueStringValue) {
            if (strcasecmp($envVarVal, $trueStringValue) === 0) {
                return true;
            }
        }
        foreach (['false', 'no', 'off', '0'] as $falseStringValue) {
            if (strcasecmp($envVarVal, $falseStringValue) === 0) {
                return false;
            }
        }

        return null;
    }
}
