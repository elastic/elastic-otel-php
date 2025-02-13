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

namespace ElasticOTelTests\Util\Log;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class StdWriteStreamBase implements LoggableInterface
{
    use LoggableTrait;

    private ?bool $isDefined = null;

    /** @var ?resource */
    private $stream = null;

    public function __construct(
        private readonly string $streamName
    ) {
    }

    private function globalConstantName(): string
    {
        return strtoupper($this->streamName);
    }

    /**
     * @return bool
     *
     * @phpstan-assert-if-true !null $this->stream
     */
    private function ensureIsDefined(): bool
    {
        $globalConstantName = $this->globalConstantName();
        if ($this->isDefined === null) {
            if (defined(strtoupper($this->streamName))) {
                $this->isDefined = true;
            } else {
                define($globalConstantName, fopen('php://' . $this->streamName, 'w'));
                $this->isDefined = defined($globalConstantName);
            }
        }

        if ($this->isDefined) {
            $globalConstantValue = constant($this->globalConstantName());
            if (is_resource($globalConstantValue)) {
                $this->stream = $globalConstantValue;
            } else {
                $this->isDefined = false;
            }
        }

        return $this->isDefined;
    }

    public function writeLine(string $text): void
    {
        if ($this->ensureIsDefined()) {
            fwrite($this->stream, $text . PHP_EOL);
            fflush($this->stream);
        }
    }
}
