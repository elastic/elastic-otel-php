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

use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LoggerFactory;
use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Clock implements ClockInterface
{
    private readonly Logger $logger;
    private ?float $lastSystemTime = null;
    private ?float $lastMonotonicTime = null;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
    }

    /**
     * @param-out ?float $last
     */
    private function checkAgainstUpdateLast(string $dbgSourceDesc, float $current, /* ref */ ?float &$last): float // @phpstan-ignore paramOut.unusedType
    {
        if ($last !== null) {
            if ($current < $last) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Detected that clock has jumped backwards'
                    . ' - returning the later time (i.e., the time further into the future) instead',
                    [
                        'time source'         => $dbgSourceDesc,
                        'last as duration'    => TimeUtil::formatDurationInMicroseconds($last),
                        'current as duration' => TimeUtil::formatDurationInMicroseconds($current),
                        'current - last'      => TimeUtil::formatDurationInMicroseconds($current - $last),
                        'last as number'      => number_format($last),
                        'current as number'   => number_format($current),
                    ]
                );
                return $last;
            }
        }
        $last = $current;
        return $current;
    }

    /** @inheritDoc */
    #[Override]
    public function getSystemClockCurrentTime(): SystemTime
    {
        // Return value should be in microseconds
        // while microtime(as_float: true) returns current Unix timestamp in seconds with microseconds being the fractional part
        return new SystemTime($this->checkAgainstUpdateLast('microtime', round(TimeUtil::secondsToMicroseconds(microtime(as_float: true))), /* ref */ $this->lastSystemTime));
    }

    /** @inheritDoc */
    #[Override]
    public function getMonotonicClockCurrentTime(): MonotonicTime
    {
        $hrtimeRetVal = floatval(hrtime(as_number: true));
        return new MonotonicTime($this->checkAgainstUpdateLast('hrtime', round(TimeUtil::nanosecondsToMicroseconds($hrtimeRetVal)), /* ref */ $this->lastMonotonicTime));
    }
}
