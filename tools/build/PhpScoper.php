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

namespace ElasticOTelTools\Build;

use Elastic\OTel\Util\ArrayUtil;
use ElasticOTelTools\ToolsLoggingClassTrait;
use ElasticOTelTools\ToolsAssertTrait;
use ElasticOTelTools\ToolsUtil;

final class PhpScoper
{
    use ToolsAssertTrait;
    use ToolsLoggingClassTrait;

    /**
     * @return array<string, mixed>
     */
    public static function topConfig(): array
    {
        /**
         * @see https://github.com/humbug/php-scoper/blob/main/docs/configuration.md
         */
        return [
            'exclude-namespaces' => [
                'PHP_CodeSniffer',
                'PHPStan',
                'PHPUnit',
            ],
            'patchers' => [
                function (string $filePath, string $prefix, string $content): string {
                    return self::patch($filePath, $prefix, $content);
                },
            ],
        ];
    }

    private static function patch(string $filePath, string $prefix, string $content): string
    {
        /**
         * @see https://github.com/humbug/php-scoper/blob/main/docs/configuration.md#patchers
         */

        /** @var ?array<string, callable(string, string): string> $patcherPerFile */
        static $patcherPerFile = null;
        if ($patcherPerFile === null) {
            // __DIR__ is tools/build/
            $repoRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
            $patcherPerFile = [
                $repoRoot . DIRECTORY_SEPARATOR . ToolsUtil::adaptUnixDirectorySeparators('vendor/composer/autoload_real.php') =>
                    self::patchComposerAutoloadReal(...),
            ];
        }
        /** @var array<string, callable(string, string): string> $patcherPerFile */

        if (ArrayUtil::getValueIfKeyExists($filePath, $patcherPerFile, /* out */ $patcher)) {
            return $patcher($prefix, $content);
        }
        return $content;
    }

    private static function patchComposerAutoloadReal(string $prefix, string $content): string
    {
        /**
         * @see https://github.com/humbug/php-scoper/blob/main/docs/configuration.md#patchers
         */

        self::logInfo(__LINE__, __METHOD__, "content:" . PHP_EOL . $content);

        $newContent = str_replace(
            '\'Composer\Autoload\ClassLoader\'',
            '\'' . $prefix . '\Composer\Autoload\ClassLoader\'',
            $content,
            /* out */ $replaceCount
        );

        self::logInfo(__LINE__, __METHOD__, "replaceCount: $replaceCount ; newContent:" . PHP_EOL . $newContent);
        self::assertSame(1, $replaceCount);

        return $newContent;
    }

    /**
     * Must be defined in class using ToolsLoggingClassTrait
     */
    private static function getCurrentSourceCodeFile(): string
    {
        return __FILE__;
    }
}
