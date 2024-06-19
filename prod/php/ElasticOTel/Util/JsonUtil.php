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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class JsonUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $data
     * @param bool  $prettyPrint
     *
     * @return string
     *
     * @throws JsonException
     */
    public static function encode($data, bool $prettyPrint = false): string
    {
        $options = JSON_INVALID_UTF8_SUBSTITUTE;
        $options |= $prettyPrint ? JSON_PRETTY_PRINT : 0;
        $encodedData = json_encode($data, $options);
        if ($encodedData === false) {
            throw new JsonException(
                'json_encode() failed'
                . '. json_last_error_msg(): ' . json_last_error_msg()
                . '. dataType: ' . DbgUtil::getType($data)
            );
        }
        return $encodedData;
    }
}