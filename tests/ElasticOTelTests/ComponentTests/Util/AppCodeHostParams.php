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

use Ds\Map;
use Elastic\OTel\Log\LogLevel;
use Elastic\OTel\PhpPartFacade;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\Config\CompositeRawSnapshotSource;
use ElasticOTelTests\Util\Config\ConfigSnapshotForProd;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\Config\OptionsForProdMetadata;
use ElasticOTelTests\Util\Config\Parser as ConfigParser;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\LoggerFactory;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 *
 * @phpstan-type OptionForProdValue string|int|float|bool
 * @phpstan-type OptionsForProdMap Map<OptionForProdName, OptionForProdValue>
 */
class AppCodeHostParams implements LoggableInterface
{
    use LoggableTrait;

    /** @var OptionsForProdMap */
    private Map $prodOptions;

    public string $spawnedProcessInternalId;

    public function __construct(
        public readonly string $dbgProcessNamePrefix
    ) {
        $this->prodOptions = new Map();
    }

    /**
     * @param OptionForProdName  $optName
     * @param OptionForProdValue $optVal
     */
    public function setProdOption(OptionForProdName $optName, string|int|float|bool $optVal): void
    {
        $this->prodOptions[$optName] = $optVal;
    }

    /**
     * @param OptionForProdName   $optName
     * @param ?OptionForProdValue $optVal
     */
    public function setProdOptionIfNotNull(OptionForProdName $optName, null|string|int|float|bool $optVal): void
    {
        if ($optVal !== null) {
            $this->setProdOption($optName, $optVal);
        }
    }

    /**
     * @param OptionsForProdMap $prodOptions
     *
     * @return bool
     */
    private static function areAnyProdLogLevelRelatedOptionsSet(Map $prodOptions): bool
    {
        return !IterableUtil::isEmpty(IterableUtil::findByPredicateOnValue(IterableUtil::keys($prodOptions), fn($optName) => $optName->isLogLevelRelated()));
    }

    private static function isProdEnvVarLogRelated(string $envVarName): bool
    {
        foreach (OptionForProdName::cases() as $optName) {
            if ($optName->isLogRelated() && $optName->toEnvVarName() === $envVarName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @phpstan-param EnvVars $inputEnvVars
     *
     * @return EnvVars
     */
    private static function removeProdLogLevelRelatedEnvVars(array $inputEnvVars): array
    {
        $outputEnvVars = $inputEnvVars;
        foreach (OptionForProdName::getAllLogLevelRelated() as $optName) {
            $envVarName = $optName->toEnvVarName();
            if (array_key_exists($envVarName, $outputEnvVars)) {
                unset($outputEnvVars[$envVarName]);
            }
        }

        return $outputEnvVars;
    }

    /**
     * @phpstan-param EnvVars           $baseEnvVars
     * @phpstan-param OptionsForProdMap $prodOptions
     *
     * @return EnvVars
     */
    private static function filterBaseEnvVars(array $baseEnvVars, Map $prodOptions): array
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyDebug = $logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        if ($loggerProxyDebug !== null) {
            ksort(/* ref */ $baseEnvVars);
            $loggerProxyDebug->log(__LINE__, 'Entered', compact('baseEnvVars'));
        }

        $areAnyProdLogLevelRelatedOptionsSet = self::areAnyProdLogLevelRelatedOptionsSet($prodOptions);
        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Before handling log related options', compact('areAnyProdLogLevelRelatedOptionsSet'));
        $envVars = $baseEnvVars;
        if ($areAnyProdLogLevelRelatedOptionsSet) {
            $envVars = self::removeProdLogLevelRelatedEnvVars($envVars);
        }
        if ($loggerProxyDebug !== null) {
            ksort(/* ref */ $envVars);
            $loggerProxyDebug->log(__LINE__, 'After handling log related options', compact('envVars'));
        }

        $result = array_filter(
            $envVars,
            function (string $envVarName): bool {
                // Return false for entries to be removed

                // Keep environment variables related to testing infrastructure
                if (TextUtil::isPrefixOfIgnoreCase(OptionForTestsName::ENV_VAR_NAME_PREFIX, $envVarName)) {
                    return true;
                }

                // Keep environment variable 'is dev mode'
                if ($envVarName === PhpPartFacade::CONFIG_ENV_VAR_NAME_DEV_INTERNAL_MODE_IS_DEV) {
                    return true;
                }

                // Keep environment variables related to production code logging
                if (self::isProdEnvVarLogRelated($envVarName)) {
                    return true;
                }

                // Keep environment variables explicitly configured to be passed through
                if (AmbientContextForTests::testConfig()->isEnvVarToPassThrough($envVarName)) {
                    return true;
                }

                // Drop any other environment variables related to either vanilla or Elastic OTel
                foreach (OptionForProdName::getEnvVarNamePrefixes() as $envVarPrefix) {
                    if (TextUtil::isPrefixOfIgnoreCase($envVarPrefix, $envVarName)) {
                        return false;
                    }
                }

                // Keep the rest
                return true;
            },
            ARRAY_FILTER_USE_KEY
        );

        if ($loggerProxyDebug !== null) {
            ksort(/* ref */ $result);
            $loggerProxyDebug->log(__LINE__, 'Exiting', compact('result'));
        }
        return $result;
    }

    /**
     * @phpstan-param EnvVars           $inheritedEnvVars
     * @phpstan-param OptionsForProdMap $prodOptions
     *
     * @return EnvVars
     */
    public static function buildEnvVarsForAppCodeProcessImpl(array $inheritedEnvVars, Map $prodOptions): array
    {
        $result = self::filterBaseEnvVars($inheritedEnvVars, $prodOptions);

        foreach ($prodOptions as $optName => $optVal) {
            $result[$optName->toEnvVarName()] = ConfigUtilForTests::optionValueToString($optVal);
        }

        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('', compact('result'));
        return $result;
    }

    /**
     * @return EnvVars
     */
    public function buildEnvVarsForAppCodeProcess(): array
    {
        return self::buildEnvVarsForAppCodeProcessImpl(EnvVarUtilForTests::getAll(), $this->prodOptions);
    }

    public function buildProdConfig(): ConfigSnapshotForProd
    {
        $envVarsToInheritSource = new MockConfigRawSnapshotSource();
        $envVars = $this->buildEnvVarsForAppCodeProcess();
        $allOptsMeta = OptionsForProdMetadata::get();
        foreach (IterableUtil::keys($allOptsMeta) as $optName) {
            $envVarName = OptionForProdName::findByName($optName)->toEnvVarName();
            if (array_key_exists($envVarName, $envVars)) {
                $envVarsToInheritSource->set($optName, $envVars[$envVarName]);
            }
        }

        $explicitlySetOptionsSource = new MockConfigRawSnapshotSource();
        foreach ($this->prodOptions as $optName => $optVal) {
            $explicitlySetOptionsSource->set($optName->name, ConfigUtilForTests::optionValueToString($optVal));
        }
        $rawSnapshotSource = new CompositeRawSnapshotSource([$explicitlySetOptionsSource, $envVarsToInheritSource]);
        $rawSnapshot = $rawSnapshotSource->currentSnapshot($allOptsMeta);

        // Set log level above ERROR to hide potential errors when parsing the provided test configuration snapshot
        $logBackend = AmbientContextForTests::loggerFactory()->getBackend()->clone();
        $logBackend->setMaxEnabledLevel(LogLevel::critical);
        $loggerFactory = new LoggerFactory($logBackend);
        $parser = new ConfigParser($loggerFactory);
        return new ConfigSnapshotForProd($parser->parse($allOptsMeta, $rawSnapshot));
    }
}
