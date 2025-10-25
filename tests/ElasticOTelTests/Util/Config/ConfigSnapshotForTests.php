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
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\ComponentTests\Util\TestGroupName;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerProcess;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerRequest;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\ExceptionUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use PHPUnit\Framework\Assert;

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

    public readonly ?TestGroupName $group; // @phpstan-ignore property.uninitializedReadonly

    public readonly LogLevel $logLevel; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $logsDirectory; // @phpstan-ignore property.uninitializedReadonly

    public readonly ?string $mysqlHost; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?int $mysqlPort; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $mysqlUser; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $mysqlPassword; // @phpstan-ignore property.uninitializedReadonly
    public readonly ?string $mysqlDb; // @phpstan-ignore property.uninitializedReadonly

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        self::setPropertiesToValuesFrom($optNameToParsedValue);

        $this->validateFileExistsIfSet(OptionForTestsName::app_code_php_exe);
        $this->validateFileExistsIfSet(OptionForTestsName::app_code_bootstrap_php_part_file);
        $this->validateFileExistsIfSet(OptionForTestsName::app_code_ext_binary);

        $this->validateDirectoryExistsOrCanBeCreatedIfSet(OptionForTestsName::logs_directory);
    }

    public function appCodeHostKind(): AppCodeHostKind
    {
        return AssertEx::notNull($this->appCodeHostKind);
    }

    public function dataPerProcess(): TestInfraDataPerProcess
    {
        return AssertEx::notNull($this->dataPerProcess);
    }

    public function dataPerRequest(): TestInfraDataPerRequest
    {
        return AssertEx::notNull($this->dataPerRequest);
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
        return $this->group === TestGroupName::smoke;
    }

    public function doesRequireExternalServices(): bool
    {
        return $this->group === null || $this->group->doesRequireExternalServices();
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
            $envVarName = $optName->toEnvVarName();
            $allEnvVars = EnvVarUtilForTests::getAll();
            ksort(/* ref */ $allEnvVars);
            throw new ConfigException(ExceptionUtil::buildMessage('Mandatory option is not set (snapshot property value is null)', compact('optName', 'envVarName', 'allEnvVars')));
        }
    }

    private function validateFileExistsIfSet(OptionForTestsName $optName): void
    {
        $propertyName = TextUtil::snakeToCamelCase($optName->name);
        $propertyValue = $this->$propertyName;
        if ($propertyValue === null) {
            return;
        }
        Assert::assertIsString($propertyValue);

        $envVarName = $optName->toEnvVarName();

        if (!file_exists($propertyValue)) {
            throw new ConfigException(
                ExceptionUtil::buildMessage('Option for a file path is set, but it points to a file that does not exist', compact('optName', 'envVarName', 'propertyValue'))
            );
        }

        if (!is_file($propertyValue)) {
            throw new ConfigException(
                ExceptionUtil::buildMessage('Option for a file path is set, but the path points to an entity that is not a regular file', compact('optName', 'envVarName', 'propertyValue'))
            );
        }
    }

    private function validateDirectoryExistsOrCanBeCreatedIfSet(OptionForTestsName $optName): void
    {
        $propertyName = TextUtil::snakeToCamelCase($optName->name);
        $propertyValue = $this->$propertyName;
        if ($propertyValue === null) {
            return;
        }
        Assert::assertIsString($propertyValue);

        $envVarName = $optName->toEnvVarName();

        if (file_exists($propertyValue)) {
            if (!is_dir($propertyValue)) {
                throw new ConfigException(
                    ExceptionUtil::buildMessage('Option for a directory path is set, but the path points to an entity that is not a directory', compact('optName', 'envVarName', 'propertyValue'))
                );
            }
            return;
        }

        if (!mkdir($propertyValue)) {
            throw new ConfigException(
                ExceptionUtil::buildMessage('Option for a directory path is set, but attempt to create the directory failed', compact('optName', 'envVarName', 'propertyValue'))
            );
        }
    }

    public function validateForComponentTests(): void
    {
        $this->validateNotNullOption(OptionForTestsName::app_code_host_kind);
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
