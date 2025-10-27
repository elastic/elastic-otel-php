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

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\ComponentTests\Util\OtlpData\SpanKind;
use ElasticOTelTests\Util\AssertEx;
use OpenTelemetry\SemConv\TraceAttributes;

class DbSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    public function __construct()
    {
        parent::__construct();

        $this->kind(SpanKind::client);
    }

    /**
     * @return $this
     */
    public function dbSystemName(string $value): self
    {
        return $this->addAttribute(TraceAttributes::DB_SYSTEM_NAME, $value);
    }

    /**
     * @return $this
     */
    public function dbNamespace(string $value): self
    {
        return $this->addAttribute(TraceAttributes::DB_NAMESPACE, $value);
    }

    /**
     * @return $this
     */
    public function dbQueryText(string $value): self
    {
        return $this->addAttribute(TraceAttributes::DB_QUERY_TEXT, $value);
    }

    /**
     * @return $this
     */
    public function dbOperationName(string $value): self
    {
        return $this->addAttribute(TraceAttributes::DB_OPERATION_NAME, $value);
    }

    /**
     * @return $this
     */
    public function dbQueryTextAndOperationName(string $value): self
    {
        return $this->dbQueryText($value)->dbOperationName(self::extractDbOperationNameFromQueryText($value));
    }

    /**
     * @return $this
     */
    public function optionalDbQueryText(?string $dbQueryText): self
    {
        if ($dbQueryText !== null) {
            return $this->dbQueryText($dbQueryText);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function optionalDbQueryTextAndOperationName(?string $dbQueryText): self
    {
        if ($dbQueryText !== null) {
            return $this->dbQueryTextAndOperationName($dbQueryText);
        }
        return $this;
    }

    private static function extractDbOperationNameFromQueryText(string $dbQueryText): string
    {
        $words = explode(' ', $dbQueryText, limit: 2);
        AssertEx::countAtLeast(1, $words);
        return $words[0];
    }
}
