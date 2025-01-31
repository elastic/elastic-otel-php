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

namespace ElasticOTelTests\Util\Config;

use BackedEnum;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\TestCaseBase;
use Override;
use UnitEnum;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template T
 *
 * @extends OptionParser<T>
 */
class EnumOptionParser extends OptionParser
{
    /**
     * We are forced to use list-array of pairs instead of regular associative array
     * because in an associative array if the key is numeric string it's automatically converted to int
     * (see https://www.php.net/manual/en/language.types.array.php)
     *
     * @param list<array{string, T}> $nameValuePairs
     */
    public function __construct(
        private readonly string $dbgDesc,
        private readonly array $nameValuePairs,
        private readonly bool $isCaseSensitive,
        private readonly bool $isUnambiguousPrefixAllowed
    ) {
    }

    /**
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum> $enumClass
     *
     * @return self<TEnum>
     */
    public static function useEnumCasesNames(string $enumClass, bool $isCaseSensitive, bool $isUnambiguousPrefixAllowed): self
    {
        $nameValuePairs = [];
        foreach ($enumClass::cases() as $enumCase) {
            $nameValuePairs[] = [$enumCase->name, $enumCase];
        }
        return new self($enumClass, $nameValuePairs, $isCaseSensitive, $isUnambiguousPrefixAllowed);
    }

    /**
     * @template TEnum of BackedEnum
     * *
     * @param class-string<TEnum> $enumClass
     *
     * @return self<TEnum>
     */
    public static function useEnumCasesValues(string $enumClass, bool $isCaseSensitive, bool $isUnambiguousPrefixAllowed): self
    {
        /** @var list<array{string, TEnum}> $nameValuePairs */
        $nameValuePairs = [];
        foreach ($enumClass::cases() as $enumCase) {
            TestCaseBase::assertIsString($enumCase->value);
            $nameValuePairs[] = [$enumCase->value, $enumCase];
        }
        return new self($enumClass, $nameValuePairs, $isCaseSensitive, $isUnambiguousPrefixAllowed);
    }

    /**
     * @return list<array{string, T}>
     */
    public function nameValuePairs(): array
    {
        return $this->nameValuePairs;
    }

    public function isCaseSensitive(): bool
    {
        return $this->isCaseSensitive;
    }

    public function isUnambiguousPrefixAllowed(): bool
    {
        return $this->isUnambiguousPrefixAllowed;
    }

    /** @inheritDoc */
    #[Override]
    public function parse(string $rawValue): mixed
    {
        /** @var ?array{string, T} $foundPair */
        $foundPair = null;
        foreach ($this->nameValuePairs as $currentPair) {
            if (TextUtil::isPrefixOf($rawValue, $currentPair[0], $this->isCaseSensitive)) {
                if (strlen($currentPair[0]) === strlen($rawValue)) {
                    return $currentPair[1];
                }

                if (!$this->isUnambiguousPrefixAllowed) {
                    continue;
                }

                if ($foundPair != null) {
                    throw new ParseException(ExceptionUtil::buildMessage('Not a valid value - it matches more than one entry as a prefix', compact('this', 'rawValue', 'foundPair', 'currentPair')));
                }
                $foundPair = $currentPair;
            }
        }

        if ($foundPair == null) {
            throw new ParseException('Not a valid ' . $this->dbgDesc . ' value. Raw option value: `$rawValue\'');
        }

        return $foundPair[1];
    }
}
