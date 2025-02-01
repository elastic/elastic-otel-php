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

use PHPUnit\Framework\AssertionFailedError;

final class PHPUnitFrameworkAssertionFailedErrorAutoloader
{
    private static bool $isClassLoaded = false;

    public static function register(): void
    {
        spl_autoload_register(
            static function (string $fqClassName): void {
                // Example of $fqClassName: PHPUnit\Framework\AssertionFailedError

                if (self::$isClassLoaded || $fqClassName !== AssertionFailedError::class) {
                    return;
                }

                require __DIR__ . '/patched/AssertionFailedError.php';

                self::$isClassLoaded = true;
            },
            prepend: true
        );
    }
}
