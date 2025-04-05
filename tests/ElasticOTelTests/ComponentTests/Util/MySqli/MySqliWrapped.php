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
use mysqli;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySqliWrapped implements LoggableInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly mysqli $wrappedObj,
        private readonly bool $isOOPApi
    ) {
    }

    public function ping(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->ping()
            : mysqli_ping($this->wrappedObj);
    }

    public function selectDb(string $dbName): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->select_db($dbName)
            : mysqli_select_db($this->wrappedObj, $dbName);
    }

    public function query(string $query): bool|MySqliResultWrapped
    {
        $result = $this->isOOPApi
            ? $this->wrappedObj->query($query)
            : mysqli_query($this->wrappedObj, $query);
        return is_bool($result) ? $result : new MySqliResultWrapped($result, $this->isOOPApi);
    }

    public function realQuery(string $query): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->real_query($query)
            : mysqli_real_query($this->wrappedObj, $query);
    }

    public function multiQuery(string $query): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->multi_query($query)
            : mysqli_multi_query($this->wrappedObj, $query);
    }

    public function moreResults(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->more_results()
            : mysqli_more_results($this->wrappedObj);
    }

    public function nextResult(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->next_result()
            : mysqli_next_result($this->wrappedObj);
    }

    public function storeResult(): false|MySqliResultWrapped
    {
        $result = $this->isOOPApi
            ? $this->wrappedObj->store_result()
            : mysqli_store_result($this->wrappedObj);
        return $result === false ? false : new MySqliResultWrapped($result, $this->isOOPApi);
    }

    public function beginTransaction(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->begin_transaction()
            : mysqli_begin_transaction($this->wrappedObj);
    }

    public function commit(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->commit()
            : mysqli_commit($this->wrappedObj);
    }

    public function rollback(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->rollback()
            : mysqli_rollback($this->wrappedObj);
    }

    public function prepare(string $query): false|MySqliStmtWrapped
    {
        $result = $this->isOOPApi
            ? $this->wrappedObj->prepare($query)
            : mysqli_prepare($this->wrappedObj, $query);
        return $result === false ? false : new MySqliStmtWrapped($result, $this->isOOPApi);
    }

    public function error(): string
    {
        return $this->isOOPApi
            ? $this->wrappedObj->error
            : mysqli_error($this->wrappedObj);
    }

    public function close(): bool
    {
        return $this->isOOPApi
            ? $this->wrappedObj->close()
            : mysqli_close($this->wrappedObj);
    }
}
