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

use PHPUnit\Framework\Assert;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PhpVersionInfo
{
    use ComparableTrait;

    private function __construct(
        private readonly int $major,
        private readonly int $minor,
    ) {
    }

    public static function fromMajorMinorNoDotString(string $majorMinorNoDotString): self
    {
        Assert::assertSame(2, strlen($majorMinorNoDotString));
        $major = substr($majorMinorNoDotString, offset:  0, length: 1);
        $minor = substr($majorMinorNoDotString, offset:  1, length: 1);
        return new self(AssertEx::stringIsInt($major), AssertEx::stringIsInt($minor));
    }

    /**
     * @return int[]
     */
    private function asParts(): array
    {
        return [$this->major, $this->minor];
    }

    public function compare(self $other): int
    {
        return NumericUtilForTests::compareSequences($this->asParts(), $other->asParts());
    }

    public function asDotSeparated(): string
    {
        return $this->major . '.' . $this->minor;
    }
}
