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

use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template TParsedValue of int|float
 *
 * @extends  OptionParser<TParsedValue>
 */
abstract class NumericOptionParser extends OptionParser
{
    /**
     * @param ?TParsedValue $minValidValue
     * @param ?TParsedValue $maxValidValue
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function __construct(
        private readonly null|int|float $minValidValue,
        private readonly null|int|float $maxValidValue,
    ) {
    }

    abstract protected function dbgValueTypeDesc(): string;

    abstract public static function isValidFormat(string $rawValue): bool;

    /**
     * @return TParsedValue
     */
    abstract protected function stringToNumber(string $rawValue): int|float;

    /** @inheritDoc */
    #[Override]
    public function parse(string $rawValue): int|float
    {
        if (!static::isValidFormat($rawValue)) {
            throw new ParseException(
                'Not a valid ' . $this->dbgValueTypeDesc() . " value. Raw option value: `''$rawValue'"
            );
        }

        $parsedValue = $this->stringToNumber($rawValue);

        if (
            (($this->minValidValue !== null) && ($parsedValue < $this->minValidValue))
            || (($this->maxValidValue !== null) && ($parsedValue > $this->maxValidValue))
        ) {
            throw new ParseException(
                'Value is not in range between the valid minimum and maximum values.'
                . ' Raw option value: `' . $rawValue . "'."
                . ' Parsed option value: ' . $parsedValue . '.'
                . ' The valid minimum value: ' . $this->minValidValue . '.'
                . ' The valid maximum value: ' . $this->maxValidValue . '.'
            );
        }

        return $parsedValue;
    }

    /**
     * @phpstan-return ?TParsedValue
     */
    public function minValidValue(): null|int|float
    {
        return $this->minValidValue;
    }

    /**
     * @phpstan-return ?TParsedValue
     */
    public function maxValidValue(): null|int|float
    {
        return $this->maxValidValue;
    }
}
