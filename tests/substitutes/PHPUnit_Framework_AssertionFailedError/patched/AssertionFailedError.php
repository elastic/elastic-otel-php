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

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace PHPUnit\Framework;

use Throwable;

/**
 * @phpstan-type PreProcessMessageCallback callable(AssertionFailedError $exceptionBeingConstructed, string $baseMessage, non-negative-int $numberOfStackFramesToSkip): string
 */
class AssertionFailedError extends Exception implements SelfDescribing
{
    /** @var ?PreProcessMessageCallback */
    public static mixed $preprocessMessage = null;

    public function __construct(string $message = '', int|string $code = 0, ?Throwable $previous = null)
    {
        if (self::$preprocessMessage !== null) {
            $message = (self::$preprocessMessage)(/* exceptionBeingConstructed */ $this, /* baseMessage */ $message, /* numberOfStackFramesToSkip */ 1);
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * Wrapper for getMessage() which is declared as final.
     */
    public function toString(): string
    {
        return $this->getMessage();
    }
}
