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

namespace ElasticOTelTests\UnitTests\UtilTests;

use Ds\Map;
use Elastic\OTel\PhpPartFacade;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionForTestsName;
use ElasticOTelTests\Util\EnvVarUtil;
use ElasticOTelTests\Util\TestCaseBase;

/**
 * @phpstan-import-type EnvVars from EnvVarUtil
 * @phpstan-import-type OptionsForProdMap from AppCodeHostParams
 */
final class ComponentTestsEnvVarsForAppCodeTest extends TestCaseBase
{
    /**
     * @param EnvVars           $inheritedEnvVars
     * @param OptionsForProdMap $prodOptions
     * @param EnvVars           $expectedBuiltEnvVars
     *
     * @noinspection PhpDocSignatureInspection
     */
    private static function buildAndAssertAsExpected(array $inheritedEnvVars, Map $prodOptions, array $expectedBuiltEnvVars): void
    {
        $actualBuiltEnvVars = AppCodeHostParams::buildEnvVarsForAppCodeProcessImpl($inheritedEnvVars, $prodOptions);
        ksort(/* ref */ $actualBuiltEnvVars);
        ksort(/* ref */ $expectedBuiltEnvVars);
        self::assertSame($expectedBuiltEnvVars, $actualBuiltEnvVars);
    }

    /**
     * @return iterable<string, array{'inheritedEnvVarNames': string[], 'prodOptionNames': OptionForProdName[]}>
     */
    public static function dataProviderForTestInheritedEnvVarsAutoPass(): iterable
    {
        /**
         * @return iterable<string, string[]>
         */
        $genInheritedEnvVarsVariants = function (): iterable {
            yield 'Unrelated to OTel/EDOT' => ['COMPOSER_BINARY', 'SHELL', 'XDG_SESSION_TYPE'];

            yield 'All options for tests' => array_map(fn($optName) => $optName->name, OptionForTestsName::cases());

            yield 'DEV_INTERNAL_MODE_IS_DEV' => [PhpPartFacade::CONFIG_ENV_VAR_NAME_DEV_INTERNAL_MODE_IS_DEV];
        };

        $allProdOptionNames = OptionForProdName::cases();
        foreach ($genInheritedEnvVarsVariants() as $inheritedEnvVarNamesDesc => $inheritedEnvVarNames) {
            foreach (['All' => $allProdOptionNames, 'None' => []] as $prodOptionNamesDesc => $prodOptionNames) {
                yield ('Inherited env vars: ' . $inheritedEnvVarNamesDesc . ', Options for production: ' . $prodOptionNamesDesc) => compact('inheritedEnvVarNames', 'prodOptionNames');
            }
        }

        // Inherited Log related (but not log related) options for production should be automatically passed through
        // except for log level related options which should be automatically passed through if and only if none of log level related production options is set
        foreach (OptionForProdName::getAllLogRelated() as $optName) {
            $inheritedEnvVarNames = [$optName->toEnvVarName()];
            $prodOptionNamesAllExceptSome = $allProdOptionNames;
            if ($optName->isLogLevelRelated()) {
                foreach (OptionForProdName::getAllLogLevelRelated() as $currentProdOptName) {
                    self::assertTrue(ArrayUtilForTests::removeFirstByValue(/* in,out */ $prodOptionNamesAllExceptSome, $currentProdOptName));
                }
            } else {
                self::assertTrue(ArrayUtilForTests::removeFirstByValue(/* in,out */ $prodOptionNamesAllExceptSome, $optName));
            }
            foreach (['All except some' => $prodOptionNamesAllExceptSome, 'None' => []] as $prodOptionNamesDesc => $prodOptionNames) {
                yield ('Inherited env vars: ' . $optName->toEnvVarName() . ', Options for production: ' . $prodOptionNamesDesc) => compact('inheritedEnvVarNames', 'prodOptionNames');
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestInheritedEnvVarsAutoPass
     *
     * @param string[]            $inheritedEnvVarNames
     * @param OptionForProdName[] $prodOptionNames
     *
     * @noinspection PhpDocSignatureInspection
     */
    public static function testInheritedEnvVarsAutoPass(array $inheritedEnvVarNames, array $prodOptionNames): void
    {
        $expectedBuiltEnvVars = [];
        /** @var OptionsForProdMap $prodOptions */
        $prodOptions = new Map();
        foreach ($prodOptionNames as $prodOptName) {
            $prodOptValue = 'value for production option ' . $prodOptName->name;
            $prodOptions->put($prodOptName, $prodOptValue);
            ArrayUtilForTests::addAssertingKeyNew($prodOptName->toEnvVarName(), $prodOptValue, /* in,out */ $expectedBuiltEnvVars);
        }

        $inheritedEnvVars = [];
        foreach ($inheritedEnvVarNames as $inheritedEnvVarName) {
            $inheritedEnvVarValue = 'value for inherited env var ' . $inheritedEnvVarName;
            ArrayUtilForTests::addAssertingKeyNew($inheritedEnvVarName, $inheritedEnvVarValue, /* in,out */ $inheritedEnvVars);
            ArrayUtilForTests::addAssertingKeyNew($inheritedEnvVarName, $inheritedEnvVarValue, /* in,out */ $expectedBuiltEnvVars);
        }

        self::buildAndAssertAsExpected($inheritedEnvVars, $prodOptions, $expectedBuiltEnvVars);
    }

    /**
     * @return iterable<string, array{OptionForProdName}>
     */
    public static function dataProviderForTestLogLevelRelatedProdOverridesInheritedEnvVars(): iterable
    {
        foreach (OptionForProdName::getAllLogLevelRelated() as $prodOptName) {
            yield $prodOptName->name => [$prodOptName];
        }
    }

    /**
     * @dataProvider dataProviderForTestLogLevelRelatedProdOverridesInheritedEnvVars
     *
     * @noinspection PhpDocSignatureInspection
     */
    public static function testLogLevelRelatedProdOptionOverridesInheritedEnvVars(OptionForProdName $prodOptName): void
    {
        $inheritedEnvVars = [];
        foreach (OptionForProdName::getAllLogLevelRelated() as $currentLogLevelRelatedProdOptName) {
            $inheritedEnvVarValue = 'value for inherited env var ' . $currentLogLevelRelatedProdOptName->toEnvVarName();
            ArrayUtilForTests::addAssertingKeyNew($currentLogLevelRelatedProdOptName->toEnvVarName(), $inheritedEnvVarValue, /* in,out */ $inheritedEnvVars);
        }

        $prodOptValue = 'value for production option ' . $prodOptName->name;
        /** @var OptionsForProdMap $prodOptions */
        $prodOptions = new Map();
        $prodOptions->put($prodOptName, $prodOptValue);
        $expectedBuiltEnvVars = [$prodOptName->toEnvVarName() => $prodOptValue];

        self::buildAndAssertAsExpected($inheritedEnvVars, $prodOptions, $expectedBuiltEnvVars);
    }

    /**
     * @return iterable<string, array{OptionForProdName[]}>
     */
    public static function dataProviderForTestInheritedEnvVarForProdOptionAllowedViaPassThrough(): iterable
    {
        foreach (OptionForProdName::cases() as $prodOptName) {
            if (!$prodOptName->isLogRelated()) {
                yield $prodOptName->name => [[$prodOptName]];
                foreach (OptionForProdName::cases() as $secondProdOptName) {
                    if (!$secondProdOptName->isLogRelated() && $secondProdOptName !== $prodOptName) {
                        yield "[$prodOptName->name, $secondProdOptName->name]" => [[$prodOptName, $secondProdOptName]];
                    }
                }
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestInheritedEnvVarForProdOptionAllowedViaPassThrough
     *
     * @param OptionForProdName[] $prodOptNames
     */
    public static function testInheritedEnvVarForProdOptionAllowedViaPassThrough(array $prodOptNames): void
    {
        /** @var OptionsForProdMap $emptyProdOptions */
        $emptyProdOptions = new Map();

        $inheritedEnvVars = [];
        foreach ($prodOptNames as $prodOptName) {
            ArrayUtilForTests::addAssertingKeyNew($prodOptName->toEnvVarName(), 'value for inherited env var ' . $prodOptName->toEnvVarName(), /* in,out */ $inheritedEnvVars);
        }

        // Without pass-though option inherited env var for production option (unless it's log related) should not be passed to app code
        self::buildAndAssertAsExpected($inheritedEnvVars, $emptyProdOptions, expectedBuiltEnvVars: []);

        $envVarNamesToPassThrough = [];
        $expectedBuiltEnvVars = [];
        foreach ($prodOptNames as $prodOptName) {
            $envVarNamesToPassThrough[] = $prodOptName->toEnvVarName();
            $passThroughOptEnvVarName = OptionForTestsName::env_vars_to_pass_through->toEnvVarName();
            $passThroughOptValue = join(',', $envVarNamesToPassThrough);
            $inheritedEnvVars[$passThroughOptEnvVarName] = $passThroughOptValue;
            $expectedBuiltEnvVars[$passThroughOptEnvVarName] = $passThroughOptValue;
            ArrayUtilForTests::addAssertingKeyNew($prodOptName->toEnvVarName(), $inheritedEnvVars[$prodOptName->toEnvVarName()], /* in,out */ $expectedBuiltEnvVars);

            $passThroughOptEnvVarValueToRestore = EnvVarUtil::get($passThroughOptEnvVarName);
            try {
                // Set env var the pass-through option for this process and recompute AmbientContextForTests::testConfig
                // because tested code depends on AmbientContextForTests::testConfig
                EnvVarUtil::set($passThroughOptEnvVarName, $passThroughOptValue);
                AmbientContextForTests::reconfigure();
                self::buildAndAssertAsExpected($inheritedEnvVars, $emptyProdOptions, $expectedBuiltEnvVars);
            } finally {
                EnvVarUtil::setOrUnsetIfValueNull($passThroughOptEnvVarName, $passThroughOptEnvVarValueToRestore);
                AmbientContextForTests::reconfigure();
            }
        }
    }
}
