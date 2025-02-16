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

namespace ElasticOTelTests\Util;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\ConfigUtilForTests;
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\StdOut;
use Override;
use PHPUnit\Event\Code\Test as PHPUnitEventCodeTest;
use PHPUnit\Event\Code\TestMethod as PHPUnitEventCodeTestMethod;
use PHPUnit\Event\Event as PHPUnitEvent;
use PHPUnit\Event\Test\ErrorTriggered as PHPUnitEventTestErrorTriggered;
use PHPUnit\Event\Test\ErrorTriggeredSubscriber as PHPUnitEventTestErrorTriggeredSubscriber;
use PHPUnit\Event\Test\ConsideredRisky as PHPUnitEventTestConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber as PHPUnitEventTestConsideredRiskySubscriber;
use PHPUnit\Event\Test\Errored as PHPUnitEventTestErrored;
use PHPUnit\Event\Test\ErroredSubscriber as PHPUnitEventTestErroredSubscriber;
use PHPUnit\Event\Test\Failed as PHPUnitEventTestFailed;
use PHPUnit\Event\Test\FailedSubscriber as PHPUnitEventTestFailedSubscriber;
use PHPUnit\Event\Test\MarkedIncomplete as PHPUnitEventTestMarkedIncomplete;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber as PHPUnitEventTestMarkedIncompleteSubscriber;
use PHPUnit\Event\Test\Passed as PHPUnitEventTestPassed;
use PHPUnit\Event\Test\PassedSubscriber as PHPUnitEventTestPassedSubscriber;
use PHPUnit\Event\Test\PhpunitErrorTriggered as PHPUnitEventTestPhpunitErrorTriggered;
use PHPUnit\Event\Test\PhpunitErrorTriggeredSubscriber as PHPUnitEventTestPhpunitErrorTriggeredSubscriber;
use PHPUnit\Event\Test\PhpunitWarningTriggered as PHPUnitEventTestPhpunitWarningTriggered;
use PHPUnit\Event\Test\PhpunitWarningTriggeredSubscriber as PHPUnitEventTestPhpunitWarningTriggeredSubscriber;
use PHPUnit\Event\Test\PhpWarningTriggered as PHPUnitEventTestPhpWarningTriggered;
use PHPUnit\Event\Test\PhpWarningTriggeredSubscriber as PHPUnitEventTestPhpWarningTriggeredSubscriber;
use PHPUnit\Event\Test\Prepared as PHPUnitEventTestPrepared;
use PHPUnit\Event\Test\PreparedSubscriber as PHPUnitEventTestPreparedSubscriber;
use PHPUnit\Event\Test\Skipped as PHPUnitEventTestSkipped;
use PHPUnit\Event\Test\SkippedSubscriber as PHPUnitEventTestSkippedSubscriber;
use PHPUnit\Event\Test\WarningTriggered as PHPUnitEventTestWarningTriggered;
use PHPUnit\Event\Test\WarningTriggeredSubscriber as PHPUnitEventTestWarningTriggeredSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class PHPUnitExtensionBase implements Extension
{
    public static SystemTime $timestampBeforeTest;
    private readonly Logger $logger;

    public static ?PHPUnitEventTestPrepared $lastBeforeTestCaseEvent = null;

    public static ?PHPUnitEvent $lastEvent = null;

    public function __construct()
    {
        ExceptionUtil::runCatchLogRethrow(
            function (): void {
                PHPUnitToLogConverters::register();
                AmbientContextForTests::assertIsInited();
                DebugContext::ensureInited();
                ConfigUtilForTests::verifyTracingIsDisabled();
            }
        );

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);

        ($loggerProxy = $this->logger->ifLevelEnabled($this->logLevelForEnvInfo(), __LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Done', ['environment variables' => EnvVarUtilForTests::getAll()]);
    }

    protected function logLevelForEnvInfo(): LogLevel
    {
        return LogLevel::debug;
    }

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $this->registerSubscribers($facade);
    }

    public function registerSubscribers(Facade $facade): void
    {
        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestPreparedSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestPrepared $event): void
                {
                    $this->extension->beforeTestCaseIsRun($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestPassedSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestPassed $event): void
                {
                    $this->extension->afterTestCasePassed($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestConsideredRiskySubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestConsideredRisky $event): void
                {
                    $this->extension->afterTestCaseConsideredRisky($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestErroredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestErrored $event): void
                {
                    $this->extension->afterTestCaseErrored($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestErrorTriggeredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestErrorTriggered $event): void
                {
                    $this->extension->afterTestCaseErrorTriggered($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestFailedSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestFailed $event): void
                {
                    $this->extension->afterTestCaseFailed($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestMarkedIncompleteSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestMarkedIncomplete $event): void
                {
                    $this->extension->afterTestCaseMarkedIncomplete($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestPhpunitErrorTriggeredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestPhpunitErrorTriggered $event): void
                {
                    $this->extension->afterTestCasePhpunitErrorTriggered($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestPhpunitWarningTriggeredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestPhpunitWarningTriggered $event): void
                {
                    $this->extension->afterTestCasePhpunitWarningTriggered($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestPhpWarningTriggeredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestPhpWarningTriggered $event): void
                {
                    $this->extension->afterTestCasePhpWarningTriggered($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestSkippedSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestSkipped $event): void
                {
                    $this->extension->afterTestCaseSkipped($event);
                }
            }
        );

        $facade->registerSubscriber(
            new class ($this) implements PHPUnitEventTestWarningTriggeredSubscriber {
                public function __construct(
                    private readonly PHPUnitExtensionBase $extension,
                ) {
                }

                #[Override]
                public function notify(PHPUnitEventTestWarningTriggered $event): void
                {
                    $this->extension->afterTestCaseWarningTriggered($event);
                }
            }
        );
    }

    private static function formatTestForText(PHPUnitEventCodeTest $object): string
    {
        if (!($object instanceof PHPUnitEventCodeTestMethod)) {
            return $object->id();
        }

        $result = $object->className() . '::' . $object->methodName();
        if ($object->testData()->hasDataFromDataProvider()) {
            $dataSetName = $object->testData()->dataFromDataProvider()->dataSetName();
            $dataSetDesc = is_int($dataSetName) ? "#$dataSetName" : $dataSetName;
            $result .= ' ' . $dataSetDesc;
        }
        return $result;
    }

    /**
     * @return ($forLog is true ? array<string, mixed> : string)
     */
    private static function formatTelemetry(PHPUnitEvent $event, bool $forLog): array|string
    {
        $prevEvent = self::$lastEvent;
        self::$lastEvent = $event;

        $durationSinceStart = $event->telemetryInfo()->durationSinceStart()->asString();
        $memoryUsageSinceStart = $event->telemetryInfo()->memoryUsageSinceStart()->bytes();
        if ($forLog) {
            $result = ["Since start" => ['duration' => $durationSinceStart, 'memory usage (bytes)' => $memoryUsageSinceStart]];
        } else {
            $result = PHP_EOL . "Since start:    [duration: $durationSinceStart, memory usage (bytes): $memoryUsageSinceStart]";
        }

        if ($prevEvent !== null) {
            $durationSincePrevious = $event->telemetryInfo()->time()->duration($prevEvent->telemetryInfo()->time())->asString();
            $memoryUsageSincePrevious = $event->telemetryInfo()->memoryUsage()->bytes() - $prevEvent->telemetryInfo()->memoryUsage()->bytes();
            if ($forLog) {
                $result += ['Since previous' => ['duration' => $durationSincePrevious, 'memory usage (bytes)' => $memoryUsageSincePrevious]];
            } else {
                $result .= PHP_EOL . "Since previous: [duration: $durationSincePrevious, memory usage (bytes): $memoryUsageSincePrevious]";
            }
        }

        return $result;
    }

    private static function formatTelemetryForText(PHPUnitEvent $event): string
    {
        return self::formatTelemetry($event, forLog: false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatTelemetryForLog(PHPUnitEvent $event): array
    {
        return self::formatTelemetry($event, forLog: true);
    }

    private static function printProgress(string $text): void
    {
        StdOut::singletonInstance()->writeLine(PHP_EOL . PHP_EOL . $text . PHP_EOL);
    }

    public function beforeTestCaseIsRun(PHPUnitEventTestPrepared $event): void
    {
        DebugContext::reset();

        self::$timestampBeforeTest = AmbientContextForTests::clock()->getSystemClockCurrentTime();

        self::$lastBeforeTestCaseEvent = $event;

        $testDesc = self::formatTestForText($event->test());
        $telemetryDesc = self::formatTelemetryForText($event);
        self::printProgress("Starting test case: $testDesc. $telemetryDesc ...");
    }

    public function afterTestCasePassed(PHPUnitEventTestPassed $event): void
    {
        $testDesc = self::formatTestForText($event->test());
        $telemetryDesc = self::formatTelemetryForText($event);
        self::printProgress("Test case passed: $testDesc...$telemetryDesc");
    }

    private function afterTestCaseDidNotPass(PHPUnitEvent $event, PHPUnitEventCodeTest $test): void
    {
        ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Test case did not pass', compact('test', 'event') + self::formatTelemetryForLog($event));
    }

    public function afterTestCaseConsideredRisky(PHPUnitEventTestConsideredRisky $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseErrored(PHPUnitEventTestErrored $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseErrorTriggered(PHPUnitEventTestErrorTriggered $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseFailed(PHPUnitEventTestFailed $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseMarkedIncomplete(PHPUnitEventTestMarkedIncomplete $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCasePhpunitErrorTriggered(PHPUnitEventTestPhpunitErrorTriggered $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCasePhpunitWarningTriggered(PHPUnitEventTestPhpunitWarningTriggered $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCasePhpWarningTriggered(PHPUnitEventTestPhpWarningTriggered $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseSkipped(PHPUnitEventTestSkipped $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }

    public function afterTestCaseWarningTriggered(PHPUnitEventTestWarningTriggered $event): void
    {
        $this->afterTestCaseDidNotPass($event, $event->test());
    }
}
