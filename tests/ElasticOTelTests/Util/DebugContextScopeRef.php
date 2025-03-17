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

/**
 * @phpstan-import-type Context from DebugContextScope
 */
final class DebugContextScopeRef
{
    public function __construct(
        private readonly DebugContextSingleton $containingStack,
        private ?DebugContextScope $scope,
    ) {
    }

    public function __destruct()
    {
        if (DebugContextConfig::useDestructors()) {
            $this->popThisScope(numberOfStackFramesToSkip: 1);
        }
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     */
    public function popThisScope(int $numberOfStackFramesToSkip = 0): void
    {
        if ($this->scope === null) {
            return;
        }

        $this->containingStack->popTopScope($this->scope, $numberOfStackFramesToSkip + 1);
        $this->scope->reset(['This scope was pop-ed' => true]);
        $this->scope = null;
    }

    /**
     * @phpstan-param Context $ctx
     */
    public function add(array $ctx): void
    {
        $this->scope?->add($ctx);
    }

    public function pushSubScope(): void
    {
        $this->scope?->pushSubScope();
    }

    /**
     * @phpstan-param Context $ctx
     */
    public function resetTopSubScope(array $ctx): void
    {
        $this->scope?->resetTopSubScope($ctx);
    }

    public function popSubScope(): void
    {
        $this->scope?->popSubScope();
    }
}
