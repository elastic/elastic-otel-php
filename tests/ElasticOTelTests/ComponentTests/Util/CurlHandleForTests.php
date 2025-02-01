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

use CurlHandle;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\TestCaseBase;
use PHPUnit\Framework\Assert;

final class CurlHandleForTests implements LoggableInterface
{
    use LoggableTrait;

    private ?CurlHandle $curlHandle;
    private ?string $lastVerboseOutput = null;

    public function __construct(
        CurlHandle $curlHandle,
        private readonly ResourcesClient $resourcesClient
    ) {
        $this->curlHandle = $curlHandle;
    }

    public function setOpt(int $option, mixed $value): bool
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_setopt($this->curlHandle, $option, $value);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function setOptArray(array $options): bool
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_setopt_array($this->curlHandle, $options);
    }

    public function exec(): string|bool
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx);
        Assert::assertNotNull($this->curlHandle);

        $verboseOutputFilePath = $this->resourcesClient->createTempFile('curl verbose output');
        $dbgCtx->add(compact('verboseOutputFilePath'));
        /** @var null|resource|false $verboseOutputFile */
        $verboseOutputFile = null;
        $isAfterCurlExec = false;
        try {
            $verboseOutputFile = fopen($verboseOutputFilePath, 'w'); // open file for write
            TestCaseBase::assertIsResource($verboseOutputFile, 'Failed to open temp file for curl verbose output; ' . LoggableToString::convert(compact('verboseOutputFilePath')));
            Assert::assertTrue($this->setOpt(CURLOPT_VERBOSE, true));
            Assert::assertTrue($this->setOpt(CURLOPT_STDERR, $verboseOutputFile));
            $retVal = curl_exec($this->curlHandle);
            $isAfterCurlExec = true;
        } finally {
            if (is_resource($verboseOutputFile)) {
                Assert::assertTrue(fflush($verboseOutputFile));
                Assert::assertTrue(fclose($verboseOutputFile));
                if ($isAfterCurlExec) {
                    $verboseOutput = file_get_contents($verboseOutputFilePath);
                    Assert::assertIsString($verboseOutput);
                    $this->lastVerboseOutput = $verboseOutput;
                }
                Assert::assertTrue(unlink($verboseOutputFilePath));
                $verboseOutputFile = null;
            }
        }

        return $retVal;
    }

    public function error(): string
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_error($this->curlHandle);
    }

    public function errno(): int
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_errno($this->curlHandle);
    }

    public function lastVerboseOutput(): ?string
    {
        return $this->lastVerboseOutput;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getInfo(): array
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_getinfo($this->curlHandle);
    }

    public function getResponseStatusCode(): mixed
    {
        Assert::assertNotNull($this->curlHandle);
        return curl_getinfo($this->curlHandle, CURLINFO_RESPONSE_CODE);
    }

    public function close(): void
    {
        Assert::assertNotNull($this->curlHandle);
        curl_close($this->curlHandle);
        $this->curlHandle = null;
    }
}
