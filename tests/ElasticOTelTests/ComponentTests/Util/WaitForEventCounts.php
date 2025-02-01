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
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\TestCaseBase;

final class WaitForEventCounts implements IsEnoughExportedDataInterface, LoggableInterface
{
    use LoggableTrait;

    private int $minSpanCount = 0;
    private int $maxSpanCount = 0;

    private readonly Logger $logger;

    /**
     * @param positive-int $min
     * @param ?positive-int $max
     */
    public static function spans(int $min, ?int $max = null): self
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());

        TestCaseBase::assertGreaterThan(0, $min);
        if ($max !== null) {
            TestCaseBase::assertLessThanOrEqual($min, $max);
        }

        $result = new WaitForEventCounts();
        $result->minSpanCount = $min;
        $result->maxSpanCount = $max ?? $min;

        $dbgCtx->pop();
        return $result;
    }

    private function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    public function isEnough(array $spans): bool
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        $dbgCtx->add(compact('this'));

        $spansCount = count($spans);
        TestCaseBase::assertLessThanOrEqual($this->maxSpanCount, $spansCount);

        $result = $spansCount >= $this->minSpanCount;
        $dbgCtx->pop();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Checked if exported data events counts reached the waited for values', compact('result', 'spansCount', 'this'));

        return $result;
    }
}
