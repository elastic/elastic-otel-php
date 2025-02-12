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

use PHPUnit\Framework\Assert;

const ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_FUNCTION = 'dummyFuncForTestsWithoutNamespace';
const ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_FILE = __FILE__;
const ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CONTINUATION_CALL_LINE = 41;

/**
 * @template TReturnValue
 *
 * @param callable(): TReturnValue $continuation
 *
 * @return TReturnValue
 */
function dummyFuncForTestsWithoutNamespace(callable $continuation)
{
    Assert::assertSame(ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_FUNCTION, __FUNCTION__);
    Assert::assertSame(ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CONTINUATION_CALL_LINE, __LINE__ + 1);
    return $continuation(); // ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CONTINUATION_CALL_LINE should be this line number
}
