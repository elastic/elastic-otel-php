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

use Elastic\OTel\Util\StaticClassTrait;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @phpstan-import-type PreProcessMessageCallback from AssertionFailedError
 *
 * @phpstan-type CallStack non-empty-list<ClassicFormatStackTraceFrame>
 * @phpstan-type ScopeContext array<string, mixed>
 * @phpstan-type ScopeNameToContext array<string, ScopeContext>
 * @phpstan-type ConfigOptionName DebugContextConfig::*_OPTION_NAME
 * @phpstan-type ConfigStore array<ConfigOptionName, bool>
 */
final class DebugContext
{
    use StaticClassTrait;

    public const THIS_CONTEXT_KEY = 'this';

    public const TEXT_ADDED_TO_ASSERTION_MESSAGE_WHEN_DISABLED = 'DebugContext is DISABLED!';

    /**
     * Out parameter is used instead of return value to make harder to discard the scope object reference
     * thus making stay alive until the scope ends
     *
     * @param ?DebugContextScopeRef &$scopeVar
     * @param ScopeContext           $initialCtx
     *
     * @param-out DebugContextScopeRef $scopeVar
     */
    public static function getCurrentScope(/* out */ ?DebugContextScopeRef &$scopeVar, array $initialCtx = []): void
    {
        DebugContextSingleton::singletonInstance()->getCurrentScope(/* out */ $scopeVar, $initialCtx, numberOfStackFramesToSkip: 1);
    }

    /**
     * @return ScopeNameToContext
     */
    public static function getContextsStack(): array
    {
        return DebugContextSingleton::singletonInstance()->getContextsStack(numberOfStackFramesToSkip: 1);
    }

    public static function reset(): void
    {
        DebugContextSingleton::singletonInstance()->reset();
    }

    public static function ensureInited(): void
    {
        DebugContextSingleton::singletonInstance();
    }

    public static function extractAddedTextFromMessage(string $message): ?string
    {
        return DebugContextSingleton::singletonInstance()->extractAddedTextFromMessage($message);
    }

    /**
     * @return ?ScopeNameToContext
     */
    public static function extractContextsStackFromMessage(string $message): ?array
    {
        return DebugContextSingleton::singletonInstance()->extractContextsStackFromMessage($message);
    }
}
