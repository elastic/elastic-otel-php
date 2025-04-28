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

use ElasticOTelTests\ComponentTests\Util\DbSpanExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\SpanExpectations;
use ElasticOTelTests\Util\ClassNameUtil;
use mysqli;
use mysqli_stmt;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySqliDbSpanDataExpectationsBuilder extends DbSpanExpectationsBuilder
{
    public const DB_SYSTEM_NAME = 'mysql';

    public function __construct(
        private readonly bool $isOOPApi,
    ) {
        parent::__construct();

        $this->dbSystemName(self::DB_SYSTEM_NAME);
    }

    private static function deduceFuncName(string $className, string $methodName): string
    {
        return $className . '_' . $methodName;
    }

    public function buildForApi(string $className, string $methodName, ?string $funcName = null, ?string $dbQueryText = null): SpanExpectations
    {
        $builderClone = clone $this;
        $builderClone->isOOPApi
            ? $builderClone->nameAndCodeAttributesUsingClassMethod($className, $methodName, isStaticMethod: false)
            : $builderClone->nameAndCodeAttributesUsingFuncName($funcName ?? self::deduceFuncName($className, $methodName));
        $builderClone->optionalDbQueryText($dbQueryText);
        return $builderClone->build();
    }

    public function buildForMySqliClassMethod(string $methodName, ?string $funcName = null, ?string $dbQueryText = null): SpanExpectations
    {
        return $this->buildForApi(ClassNameUtil::fqToShort(mysqli::class), $methodName, $funcName, $dbQueryText);
    }

    public function buildForMySqliStmtClassMethod(string $methodName, ?string $funcName = null, ?string $dbQueryText = null): SpanExpectations
    {
        return $this->buildForApi(ClassNameUtil::fqToShort(mysqli_stmt::class), $methodName, $funcName, $dbQueryText);
    }
}
