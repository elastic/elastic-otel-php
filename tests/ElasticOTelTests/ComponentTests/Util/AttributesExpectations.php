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

use ElasticOTelTests\ComponentTests\Util\OtlpData\Attributes;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use Override;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-import-type ArrayValue from AttributesArrayExpectations
 */
final class AttributesExpectations implements ExpectationsInterface, LoggableInterface
{
    use ExpectationsTrait;
    use LoggableTrait;

    public readonly AttributesArrayExpectations $arrayExpectations;

    /**
     * @param array<string, ArrayValue> $attributes
     * @param array<string>             $notAllowedAttributes
     */
    public function __construct(
        array $attributes,
        bool $allowOtherKeysInActual = true,
        private readonly array $notAllowedAttributes = []
    ) {
        $this->arrayExpectations = new AttributesArrayExpectations($attributes, $allowOtherKeysInActual);
    }

    public static function matchAny(): self
    {
        /** @var ?self $cached */
        static $cached = null;
        return $cached ??= new self([], allowOtherKeysInActual: true);
    }

    /**
     * @phpstan-param ArrayValue $value
     */
    public function with(string $key, array|bool|float|int|null|string|ExpectationsInterface $value): self
    {
        return new self($this->arrayExpectations->add($key, $value)->expectedArray, $this->arrayExpectations->allowOtherKeysInActual, $this->notAllowedAttributes);
    }

    public function withNotAllowed(string $key): self
    {
        $notAllowedAttributes = $this->notAllowedAttributes;
        $notAllowedAttributes[] = $key;
        return new self($this->arrayExpectations->expectedArray, $this->arrayExpectations->allowOtherKeysInActual, $notAllowedAttributes);
    }

    #[Override]
    public function assertMatchesMixed(mixed $actual): void
    {
        Assert::assertInstanceOf(Attributes::class, $actual);
        $this->assertMatches($actual);
    }

    public function assertMatches(Attributes $actual): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $this->arrayExpectations->assertMatches($actual);

        $dbgCtx->pushSubScope();
        foreach ($this->notAllowedAttributes as $notAllowedAttributeName) {
            $dbgCtx->resetTopSubScope(compact('notAllowedAttributeName'));
            Assert::assertFalse($actual->keyExists($notAllowedAttributeName));
        }
        $dbgCtx->popSubScope();
    }
}
