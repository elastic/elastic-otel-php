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

use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\Util\TextUtil;
use Elastic\OTel\Util\WildcardListMatcher;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostKind;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerProcess;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerRequest;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\TestCaseBase;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ConfigSnapshotForTests implements LoggableInterface
{
    use SnapshotTrait;

    public readonly ?string $appCodeBootstrapPhpPartFile; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $appCodeExtBinary; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?AppCodeHostKind $appCodeHostKind; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $appCodePhpExe; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?TestInfraDataPerProcess $dataPerProcess; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?TestInfraDataPerRequest $dataPerRequest; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?WildcardListMatcher $envVarsToPassThrough; // @phpstan-ignore property.uninitializedReadonly
    public readonly int $escalatedRerunsMaxCount; // @phpstan-ignore property.uninitializedReadonly
    private readonly ?string $escalatedRerunsProdCodeLogLevelOptionName; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $group; // @phpstan-ignore property.uninitializedReadonly
    public readonly LogLevel $logLevel; // @phpstan-ignore property.uninitializedReadonly

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        self::setPropertiesToValuesFrom($optNameToParsedValue);

        $this->validateFileExistsIfSet(OptionForTestsName::app_code_php_exe);
        $this->validateFileExistsIfSet(OptionForTestsName::app_code_bootstrap_php_part_file);
        $this->validateFileExistsIfSet(OptionForTestsName::app_code_ext_binary);
    }

    /**
     * @template T
     *
     * @param ?T $propValue
     *
     * @return T
     */
    private static function assertNotNull(mixed $propValue): mixed
    {
        TestCaseBase::assertNotNull($propValue);
        return $propValue;
    }

    public function appCodeHostKind(): AppCodeHostKind
    {
        return self::assertNotNull($this->appCodeHostKind);
    }

    public function dataPerProcess(): TestInfraDataPerProcess
    {
        return self::assertNotNull($this->dataPerProcess);
    }

    public function dataPerRequest(): TestInfraDataPerRequest
    {
        return self::assertNotNull($this->dataPerRequest);
    }

    public function isEnvVarToPassThrough(string $envVarName): bool
    {
        if ($this->envVarsToPassThrough === null) {
            return false;
        }

        return $this->envVarsToPassThrough->match($envVarName) !== null;
    }

    public function isSmoke(): bool
    {
        return $this->group === 'smoke';
    }

    public function escalatedRerunsProdCodeLogLevelOptionName(): ?OptionForProdName
    {
        if ($this->escalatedRerunsProdCodeLogLevelOptionName === null) {
            return null;
        }

        /** @var ?OptionForProdName $result */
        static $result = null;

        if ($result === null) {
            $result = OptionForProdName::findByName($this->escalatedRerunsProdCodeLogLevelOptionName);
        }
        return $result;
    }

    private function validateNotNullOption(OptionForTestsName $optName): void
    {
        $propertyName = TextUtil::snakeToCamelCase($optName->name);
        $propertyValue = $this->$propertyName;
        if ($propertyValue === null) {
            $envVarName = OptionForTestsName::toEnvVarName($optName);
            throw new ConfigException(ExceptionUtil::buildMessage('Mandatory option is not set (snapshot property value is null)', compact('optName', 'envVarName')));
        }
    }

    private function validateFileExistsIfSet(OptionForTestsName $optName): void
    {
        $propertyName = TextUtil::snakeToCamelCase($optName->name);
        $propertyValue = $this->$propertyName;
        if ($propertyValue !== null) {
            TestCaseBase::assertIsString($propertyValue);
            if (!file_exists($propertyValue)) {
                $envVarName = OptionForTestsName::toEnvVarName($optName);
                throw new ConfigException(
                    ExceptionUtil::buildMessage('Option for a file path is set but it points to a file that does not exist', compact('optName', 'envVarName', 'propertyValue'))
                );
            }
        }
    }

    public function validateForSpawnedProcess(): void
    {
        $this->validateNotNullOption(OptionForTestsName::data_per_process);
    }

    public function validateForAppCode(): void
    {
        $this->validateForSpawnedProcess();
        $this->validateNotNullOption(OptionForTestsName::app_code_host_kind);
    }

    public function validateForAppCodeRequest(): void
    {
        $this->validateForAppCode();
        $this->validateNotNullOption(OptionForTestsName::data_per_request);
    }
}
