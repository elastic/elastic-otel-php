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

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ClassicFormatStackTraceFrame implements LoggableInterface
{
    /**
     * @param ?string      $file
     * @param ?int         $line
     * @param ?string      $class
     * @param ?bool        $isStaticMethod
     * @param ?string      $function
     * @param ?object      $thisObj
     * @param null|mixed[] $args
     *
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    public function __construct(
        public ?string $file = null,
        public ?int $line = null,
        public ?string $class = null,
        public ?bool $isStaticMethod = null,
        public ?string $function = null,
        public ?object $thisObj = null,
        public ?array $args = null
    ) {
    }

    #[Override]
    public function toLog(LogStreamInterface $stream): void
    {
        $nonNullProps = [];
        foreach (get_object_vars($this) as $propName => $propVal) {
            if ($propVal === null || $propName === 'isStaticMethod') {
                continue;
            }
            $nonNullProps[$propName] = $propVal;
        }
        $stream->toLogAs($nonNullProps);
    }
}
