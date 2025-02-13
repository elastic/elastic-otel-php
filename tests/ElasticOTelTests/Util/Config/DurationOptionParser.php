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

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\Duration;
use ElasticOTelTests\Util\DurationUnit;
use ElasticOTelTests\Util\ExceptionUtil;
use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<Duration>
 */
final class DurationOptionParser extends OptionParser
{
    public function __construct(
        public readonly ?Duration $minValidValue,
        public readonly ?Duration $maxValidValue,
        public readonly DurationUnit $defaultUnits
    ) {
    }

    /** @inheritDoc */
    #[Override]
    public function parse(string $rawValue): Duration
    {
        $partWithoutSuffix = '';
        $units = $this->defaultUnits;
        self::splitToValueAndUnits($rawValue, /* ref */ $partWithoutSuffix, /* ref */ $units);

        $auxFloatOptionParser = new FloatOptionParser(null /* minValidValue */, null /* maxValidValue */);
        $parsedValue = new Duration($auxFloatOptionParser->parse($partWithoutSuffix), $units);

        if (
            (($this->minValidValue !== null) && ($this->minValidValue->compare($parsedValue) > 0))
            ||
            (($this->maxValidValue !== null) && ($this->maxValidValue->compare($parsedValue) < 0))
        ) {
            throw new ParseException(
                ExceptionUtil::buildMessage(
                    'Value is not in range between the valid minimum and maximum values',
                    array_merge(compact('rawValue', 'parsedValue'), ['minValidValue' => $this->minValidValue, 'maxValidValue' => $this->maxValidValue])
                )
            );
        }

        return $parsedValue;
    }

    private static function splitToValueAndUnits(string $rawValue, string &$partWithoutSuffix, DurationUnit &$units): void
    {
        foreach (DurationUnit::cases() as $durationUnit) {
            $suffix = $durationUnit->name;
            if (TextUtil::isSuffixOf($suffix, $rawValue, isCaseSensitive: false)) {
                $partWithoutSuffix = trim(substr($rawValue, 0, -strlen($suffix)));
                $units = $durationUnit;
                return;
            }
        }
        $partWithoutSuffix = $rawValue;
    }
}
