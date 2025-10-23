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

namespace ElasticOTelTests\ComponentTests;

use Composer\InstalledVersions;
use Elastic\OTel\PhpPartVersion;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\AttributesExpectations;
use ElasticOTelTests\ComponentTests\Util\WaitForEventCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\MixedMap;
use OpenTelemetry\SemConv\ResourceAttributes;
use PHPUnit\Framework\Assert;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class SdkDistroAttributesComponentTest extends ComponentTestCaseBase
{
    private const SHOULD_SET_SERVICE_NAME_KEY = 'should_set_service_name';
    private const SHOULD_SET_SERVICE_VERSION_KEY = 'should_set_service_version';

    private const SERVICE_NAME = 'my_service';
    private const SERVICE_VERSION = '1.2.3-dirty/1.2.3';

    private const DEFAULT_SERVICE_NAME = 'unknown_service:php';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestAttributes(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generateDataSets = function (): iterable {
            foreach (BoolUtil::ALL_VALUES as $shouldSetServiceName) {
                $shouldSetServiceVersionVariants = $shouldSetServiceName ? BoolUtil::ALL_VALUES : [false];
                foreach ($shouldSetServiceVersionVariants as $shouldSetServiceVersion) {
                    yield [
                        self::SHOULD_SET_SERVICE_NAME_KEY => $shouldSetServiceName,
                        self::SHOULD_SET_SERVICE_VERSION_KEY => $shouldSetServiceVersion,
                    ];
                }
            }
        };

        return self::adaptDataSetsGeneratorToSmokeToDescToMixedMap($generateDataSets);
    }

    public static function buildOTelResourceAttributesForAppProcess(MixedMap $testArgs): string
    {
        $result = '';
        $addToResult = function (string $key, string $value) use (&$result): void {
            if ($result !== '') {
                $result .= ',';
            }
            $result .= ($key . '=' . $value);
        };

        if ($testArgs->getBool(self::SHOULD_SET_SERVICE_NAME_KEY)) {
            $addToResult(ResourceAttributes::SERVICE_NAME, self::SERVICE_NAME);
        }
        if ($testArgs->getBool(self::SHOULD_SET_SERVICE_VERSION_KEY)) {
            $addToResult(ResourceAttributes::SERVICE_VERSION, self::SERVICE_VERSION);
        }

        return $result;
    }

    public static function appCodeForTestAttributes(MixedMap $appCodeArgs): void
    {
        self::appCodeSetsHowFinishedAttributes($appCodeArgs);
    }

    private static function getOTelSdkVersion(): string
    {
        $otelSdkPackageName = 'open-telemetry/sdk';
        Assert::assertTrue(InstalledVersions::isInstalled($otelSdkPackageName));
        return AssertEx::notNull(InstalledVersions::getPrettyVersion($otelSdkPackageName));
    }

    public function implTestAttributes(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                self::ensureTransactionSpanEnabled($appCodeParams);
                $appCodeParams->setProdOption(OptionForProdName::resource_attributes, self::buildOTelResourceAttributesForAppProcess($testArgs));
            }
        );
        $appCodeHost->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAttributes']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        /**
         * @see https://github.com/elastic/apm/blob/9a8390a161db1cab0f7e27f03111ff4bececf523/specs/agents/otel-distribution.md?plain=1#L79
         * @see https://github.com/elastic/opentelemetry-lib/blob/434982a9d78a9b0ee1f47bccb9f03d6b7bf3570f/enrichments/internal/elastic/resource.go#L102
         * @see https://github.com/elastic/kibana/blob/v9.1.0/x-pack/solutions/observability/plugins/apm/common/agent_configuration/setting_definitions/edot_sdk_settings.ts
         *
         * - `telemetry.distro.name`: must be set to `elastic`
         * - `telemetry.distro.version`: must reflect the distribution version
         * - `agent.name`: is built by Elastic's ingestion as <telemetry.sdk.name>/<telemetry.sdk.language>/<telemetry.distro.name> and expected by Kibana to be `opentelemetry/php/elastic`
         */

        $expectedResourceAttributes = [
            ResourceAttributes::TELEMETRY_DISTRO_NAME    => 'elastic',
            ResourceAttributes::TELEMETRY_DISTRO_VERSION => PhpPartVersion::VALUE,
            ResourceAttributes::TELEMETRY_SDK_LANGUAGE   => 'php',
            ResourceAttributes::TELEMETRY_SDK_NAME       => 'opentelemetry',
            ResourceAttributes::TELEMETRY_SDK_VERSION    => self::getOTelSdkVersion(),
        ];
        $notExpectedAttributes = [];

        $expectedResourceAttributes[ResourceAttributes::SERVICE_NAME] = $testArgs->getBool(self::SHOULD_SET_SERVICE_NAME_KEY) ? self::SERVICE_NAME : self::DEFAULT_SERVICE_NAME;

        if ($testArgs->getBool(self::SHOULD_SET_SERVICE_VERSION_KEY)) {
            $expectedResourceAttributes[ResourceAttributes::SERVICE_VERSION] = self::SERVICE_VERSION;
        } else {
            $notExpectedAttributes[] = ResourceAttributes::SERVICE_VERSION;
        }

        $exportedData = $testCaseHandle->waitForEnoughExportedData(WaitForEventCounts::spans(1)); // exactly 1 span (the root span) is expected
        $dbgCtx->add(compact('exportedData'));

        // Assert

        $rootSpan = $exportedData->singleRootSpan();
        $dbgCtx->add(compact('rootSpan'));
        (new AttributesExpectations(attributes: [self::DID_APP_CODE_FINISH_SUCCESSFULLY_KEY => true], notAllowedAttributes: $notExpectedAttributes))->assertMatches($rootSpan->attributes);

        $resources = IterableUtil::toList($exportedData->resources());
        $dbgCtx->add(compact('resources'));
        AssertEx::isPositiveInt(count($resources));
        $resourceAttributesExpectations = new AttributesExpectations(attributes: $expectedResourceAttributes, notAllowedAttributes: $notExpectedAttributes);
        foreach ($resources as $resource) {
            $resourceAttributesExpectations->assertMatches($resource->attributes);
        }
    }

    /**
     * @dataProvider dataProviderForTestAttributes
     */
    public function testAttributes(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestAttributes($testArgs);
            }
        );
    }
}
