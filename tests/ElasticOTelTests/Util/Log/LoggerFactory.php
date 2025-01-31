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

use Elastic\OTel\Log\LogLevel;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggerFactory
{
    private Backend $backend;

    /** @var array<string, mixed> */
    public array $context;

    /**
     * @param Backend              $backend
     * @param array<string, mixed> $context
     */
    public function __construct(Backend $backend, array $context = [])
    {
        $this->backend = $backend;
        $this->context = $context;
    }

    /**
     * @param class-string $fqClassName
     */
    public function loggerForClass(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile
    ): Logger {
        return Logger::makeRoot($category, $namespace, $fqClassName, $srcCodeFile, $this->context, $this->backend);
    }

    public function getBackend(): Backend
    {
        return $this->backend;
    }

    /** @noinspection PhpUnused */
    public function isEnabledForLevel(LogLevel $level): bool
    {
        return $this->backend->isEnabledForLevel($level);
    }

    public function inherit(): self
    {
        return new self($this->backend);
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $keyValuePairs
     *
     * @return self
     *
     * @noinspection PhpUnused
     */
    public function addAllContext(array $keyValuePairs): self
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->addContext($key, $value);
        }
        return $this;
    }
}
