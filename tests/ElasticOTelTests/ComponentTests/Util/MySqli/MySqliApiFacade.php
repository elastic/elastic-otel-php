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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use mysqli;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySqliApiFacade implements LoggableInterface
{
    use LoggableTrait;

    private Logger $logger;

    public function __construct(
        private readonly bool $isOOPApi
    ) {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);
    }

    public function connect(string $host, int $port, string $username, string $password, ?string $dbName): ?MySqliWrapped
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', compact('host', 'port', 'username', 'password', 'dbName'));

        $wrappedObj = $this->isOOPApi
            ? new mysqli($host, $username, $password, $dbName, $port)
            : mysqli_connect($host, $username, $password, $dbName, $port);
        return ($wrappedObj instanceof mysqli) ? new MySqliWrapped($wrappedObj, $this->isOOPApi) : null;
    }
}
