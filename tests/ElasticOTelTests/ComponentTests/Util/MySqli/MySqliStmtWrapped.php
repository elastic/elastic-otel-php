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

namespace ElasticOTelTests\ComponentTests\Util\MySqli;

use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use mysqli_stmt;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySqliStmtWrapped implements LoggableInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly mysqli_stmt $wrappedObj,
        private readonly bool $isOOPApi
    ) {
    }

    public function bindParam(string $types, mixed &$var, mixed &...$vars): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->bind_param($types, $var, ...$vars)
            : mysqli_stmt_bind_param($this->wrappedObj, $types, $var, ...$vars);
    }

    public function execute(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->execute()
            : mysqli_stmt_execute($this->wrappedObj);
    }

    public function close(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->close()
            : mysqli_stmt_close($this->wrappedObj);
    }
}
