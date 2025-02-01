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

final class DebugContextForTestsScopeAutoRef
{
    private DebugContextForTests $stack;

    private ?DebugContextForTestsScopeData $data;

    public function __construct(DebugContextForTests $stack, ?DebugContextForTestsScopeData $data)
    {
        $this->stack = $stack;
        $this->data = $data;
    }

    public function __destruct()
    {
        $this->pop();
    }

    public function pop(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->stack->popTopScope($this->data);
        $this->data = null;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function add(array $ctx): void
    {
        if ($this->data === null) {
            return;
        }

        ArrayUtilForTests::append(from: $ctx, to: $this->data->subScopesStack[count($this->data->subScopesStack) - 1]->second);
    }

    /**
     * @param array<string, mixed> $initialCtx
     */
    public function pushSubScope(array $initialCtx = []): void
    {
        if ($this->data === null) {
            return;
        }

        TestCaseBase::assertGreaterThanOrEqual(1, count($this->data->subScopesStack));
        $this->data->subScopesStack[] = new Pair(DebugContextForTestsScopeData::buildContextName(numberOfStackFramesToSkip: 1), $initialCtx);
        TestCaseBase::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
    }

    /**
     * @param array<string, mixed> $initialCtx
     */
    public function clearCurrentSubScope(array $initialCtx = []): void
    {
        if ($this->data === null) {
            return;
        }

        TestCaseBase::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
        $this->data->subScopesStack[count($this->data->subScopesStack) - 1]->second = $initialCtx;
    }

    public function popSubScope(): void
    {
        if ($this->data === null) {
            return;
        }

        TestCaseBase::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
        array_pop(/* ref */ $this->data->subScopesStack);
        TestCaseBase::assertGreaterThanOrEqual(1, count($this->data->subScopesStack));
    }
}
