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

use Elastic\OTel\PhpPartFacade;
use Elastic\OTel\Util\StaticClassTrait;
use ElasticOTelTests\Util\BoolUtilForTests;
use ElasticOTelTests\Util\Config\ConfigSnapshotForTests;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\Config\OptionsForTestsMetadata;
use ElasticOTelTests\Util\Config\Parser;
use ElasticOTelTests\Util\Config\RawSnapshotSourceInterface;
use ElasticOTelTests\Util\ElasticOTelExtensionUtil;
use ElasticOTelTests\Util\Log\LoggerFactory;
use ElasticOTelTests\Util\TestsInfraException;

use function elastic_otel_is_enabled;

final class ConfigUtilForTests
{
    use StaticClassTrait;

    public const PROD_DISABLED_INSTRUMENTATIONS_ALL = 'all';

    public static function read(RawSnapshotSourceInterface $configSource, LoggerFactory $loggerFactory): ConfigSnapshotForTests
    {
        $parser = new Parser($loggerFactory);
        $allOptsMeta = OptionsForTestsMetadata::get();
        $optNameToParsedValue = $parser->parse($allOptsMeta, $configSource->currentSnapshot($allOptsMeta));
        return new ConfigSnapshotForTests($optNameToParsedValue);
    }

    public static function verifyTracingIsDisabled(): void
    {
        if (!ElasticOTelExtensionUtil::isLoaded()) {
            return;
        }

        $envVarName = OptionForProdName::enabled->toEnvVarName();
        $envVarValue = EnvVarUtilForTests::get($envVarName);
        if ($envVarValue !== 'false') {
            throw new TestsInfraException(
                'Environment variable ' . $envVarName . ' should be set to `false\'.'
                . ' Instead it is ' . ($envVarValue === null ? 'not set' : 'set to `' . $envVarValue . '\'')
            );
        }

        $msgPrefix = 'Component tests auxiliary processes should not be recorded';
        // elastic_otel_is_enabled is provided by the extension
        if (function_exists('elastic_otel_is_enabled') && elastic_otel_is_enabled()) {
            throw new ComponentTestsInfraException($msgPrefix . '; elastic_otel_is_enabled() returned true');
        }
        if (PhpPartFacade::$wasBootstrapCalled) {
            throw new ComponentTestsInfraException($msgPrefix . '; PhpPartFacade::$wasBootstrapCalled is true');
        }
    }

    public static function optionValueToString(string|int|float|bool $optVal): string
    {
        if (is_string($optVal)) {
            return $optVal;
        }

        if (is_bool($optVal)) {
            return BoolUtil::toString($optVal);
        }

        return strval($optVal);
    }
}
