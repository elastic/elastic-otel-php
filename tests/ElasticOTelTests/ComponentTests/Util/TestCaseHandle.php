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

use Closure;
use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\TimeUtil;
use PHPUnit\Framework\Assert;

final class TestCaseHandle implements LoggableInterface
{
    use LoggableTrait;

    public const MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS = 3 * HttpClientUtilForTests::MAX_WAIT_TIME_SECONDS;

    private ResourcesCleanerHandle $resourcesCleaner;

    private MockOTelCollectorHandle $mockOTelCollector;

    /** @var AppCodeInvocation[] */
    public array $appCodeInvocations = [];

    protected ?AppCodeHostHandle $mainAppCodeHost = null;

    protected ?HttpAppCodeHostHandle $additionalHttpAppCodeHost = null;

    private readonly Logger $logger;

    /** @var int[] */
    private array $portsInUse;

    public function __construct(
        private readonly ?LogLevel $escalatedLogLevelForProdCode,
    ) {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));

        $globalTestInfra = ComponentTestsPHPUnitExtension::getGlobalTestInfra();
        $globalTestInfra->onTestStart();
        $this->resourcesCleaner = $globalTestInfra->getResourcesCleaner();
        $this->mockOTelCollector = $globalTestInfra->getMockOTelCollector();
        $this->portsInUse = $globalTestInfra->getPortsInUse();
    }

    /**
     * @param null|Closure(AppCodeHostParams): void $setParamsFunc
     */
    public function ensureMainAppCodeHost(?Closure $setParamsFunc = null, string $dbgInstanceName = 'main'): AppCodeHostHandle
    {
        if ($this->mainAppCodeHost === null) {
            $this->mainAppCodeHost = $this->startAppCodeHost(
                function (AppCodeHostParams $params) use ($setParamsFunc): void {
                    $this->autoSetProdOptions($params);
                    if ($setParamsFunc !== null) {
                        $setParamsFunc($params);
                    }
                },
                $dbgInstanceName
            );
        }
        return $this->mainAppCodeHost;
    }

    /**
     * @param null|Closure(HttpAppCodeHostParams): void $setParamsFunc
     *
     * @noinspection PhpUnused
     */
    public function ensureAdditionalHttpAppCodeHost(string $dbgInstanceName, ?Closure $setParamsFunc = null): HttpAppCodeHostHandle
    {
        if ($this->additionalHttpAppCodeHost === null) {
            $this->additionalHttpAppCodeHost = $this->startBuiltinHttpServerAppCodeHost(
                function (HttpAppCodeHostParams $appCodeHostParams) use ($setParamsFunc): void {
                    $this->autoSetProdOptions($appCodeHostParams);
                    if ($setParamsFunc !== null) {
                        $setParamsFunc($appCodeHostParams);
                    }
                },
                $dbgInstanceName
            );
        }
        return $this->additionalHttpAppCodeHost;
    }

    public function waitForEnoughAgentBackendComms(IsEnoughAgentBackendCommsInterface $expectedIsEnough): AgentBackendComms
    {
        Assert::assertNotEmpty($this->appCodeInvocations);
        $accumulator = new AgentBackendCommsAccumulator();
        $hasPassed = (new PollingCheck(__FUNCTION__ . ' passes', intval(TimeUtil::secondsToMicroseconds(self::MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS))))->run(
            function () use ($expectedIsEnough, $accumulator) {
                $accumulator->addEvents($this->mockOTelCollector->fetchNewAgentBackendCommEvents(shouldWait: true));
                return $accumulator->isEnough($expectedIsEnough);
            }
        );

        $accumulatedData = $accumulator->getResult();
        if (!$hasPassed) {
            DebugContext::getCurrentScope(/* out */ $dbgCtx);
            $accumulatedDataSummary = $accumulatedData->dbgGetSummary();
            $dbgCtx->add(compact('expectedIsEnough', 'accumulatedDataSummary', 'accumulatedData', 'accumulator'));
            Assert::fail('The expected exported data has not arrived; ' . LoggableToString::convert(compact('expectedIsEnough', 'accumulatedDataSummary')));
        }

        return $accumulatedData;
    }

    private function autoSetProdOptions(AppCodeHostParams $params): void
    {
        if ($this->escalatedLogLevelForProdCode !== null) {
            $escalatedLogLevelForProdCodeAsString = $this->escalatedLogLevelForProdCode->name;
            $params->setProdOption(AmbientContextForTests::testConfig()->escalatedRerunsProdCodeLogLevelOptionName() ?? OptionForProdName::log_level_syslog, $escalatedLogLevelForProdCodeAsString);
        }
        /** @noinspection HttpUrlsUsage */
        $params->setProdOption(OptionForProdName::exporter_otlp_endpoint, 'http://' . HttpServerHandle::CLIENT_LOCALHOST_ADDRESS . ':' . $this->mockOTelCollector->getPortForAgent());
    }

    public function addAppCodeInvocation(AppCodeInvocation $appCodeInvocation): void
    {
        $appCodeInvocation->appCodeHostsParams = [];
        if ($this->mainAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->mainAppCodeHost->appCodeHostParams;
        }
        if ($this->additionalHttpAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->additionalHttpAppCodeHost->appCodeHostParams;
        }
        $this->appCodeInvocations[] = $appCodeInvocation;
    }

    /**
     * @return list<LogLevel>
     */
    public function getProdCodeLogLevels(): array
    {
        $result = [];
        /** @var ?AppCodeHostHandle $appCodeHost */
        foreach ([$this->mainAppCodeHost, $this->additionalHttpAppCodeHost] as $appCodeHost) {
            if ($appCodeHost !== null) {
                $result[] = $appCodeHost->appCodeHostParams->buildProdConfig()->effectiveLogLevel();
            }
        }
        return $result;
    }

    public function tearDown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Tearing down...');

        ComponentTestsPHPUnitExtension::getGlobalTestInfra()->onTestEnd();
    }

    /**
     * @param int[] $ports
     *
     * @return void
     */
    private function addPortsInUse(array $ports): void
    {
        foreach ($ports as $port) {
            Assert::assertNotContains($port, $this->portsInUse);
            $this->portsInUse[] = $port;
        }
    }

    private function startBuiltinHttpServerAppCodeHost(Closure $setParamsFunc, string $dbgInstanceName): BuiltinHttpServerAppCodeHostHandle
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting built-in HTTP server to host app code ...', compact('dbgInstanceName'));

        $result = new BuiltinHttpServerAppCodeHostHandle($this, $setParamsFunc, $this->resourcesCleaner, $this->portsInUse, $dbgInstanceName);
        $this->addPortsInUse($result->httpServerHandle->ports);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Started built-in HTTP server to host app code', ['ports' => $result->httpServerHandle->ports]);

        return $result;
    }

    /**
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     */
    private function startAppCodeHost(Closure $setParamsFunc, string $dbgInstanceName): AppCodeHostHandle
    {
        return match (AmbientContextForTests::testConfig()->appCodeHostKind()) {
            AppCodeHostKind::cliScript => new CliScriptAppCodeHostHandle($this, $setParamsFunc, $this->resourcesCleaner, $dbgInstanceName),
            AppCodeHostKind::builtinHttpServer => $this->startBuiltinHttpServerAppCodeHost($setParamsFunc, $dbgInstanceName),
        };
    }

    public function getResourcesCleaner(): ResourcesCleanerHandle
    {
        return $this->resourcesCleaner;
    }

    public function getResourcesClient(): ResourcesClient
    {
        return $this->resourcesCleaner->getClient();
    }
}
