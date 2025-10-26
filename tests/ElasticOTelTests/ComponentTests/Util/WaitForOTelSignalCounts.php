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

use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use Override;
use PHPUnit\Framework\Assert;

final class WaitForOTelSignalCounts implements IsEnoughAgentBackendCommsInterface, LoggableInterface
{
    use LoggableTrait;

    private int $minSpanCount = 0;
    private int $maxSpanCount = 0;

    private readonly Logger $logger;

    private function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /**
     * @param positive-int $min
     * @param ?positive-int $max
     */
    public static function spans(int $min, ?int $max = null): self
    {
        Assert::assertGreaterThan(0, $min);
        if ($max !== null) {
            Assert::assertGreaterThanOrEqual($min, $max);
        }

        $result = new WaitForOTelSignalCounts();
        $result->minSpanCount = $min;
        $result->maxSpanCount = $max ?? $min;

        return $result;
    }

    /**
     * @param positive-int $min
     */
    public static function spansAtLeast(int $min): self
    {
        return self::spans(min: $min, max: PHP_INT_MAX);
    }

    #[Override]
    public function isEnough(AgentBackendComms $comms): bool
    {
        $spansCount = IterableUtil::count($comms->spans());
        Assert::assertLessThanOrEqual($this->maxSpanCount, $spansCount);

        $result = $spansCount >= $this->minSpanCount;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Checked if exported data events counts reached the waited for values', compact('result', 'spansCount', 'this'));

        return $result;
    }
}
