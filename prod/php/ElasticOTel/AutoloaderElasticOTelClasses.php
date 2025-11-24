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

namespace Elastic\OTel;

final class AutoloaderElasticOTelClasses
{
    private readonly string $autoloadFqClassNamePrefix;
    private readonly int $autoloadFqClassNamePrefixLength;
    private readonly string $srcFilePathPrefix;

    private function __construct(string $rootNamespace, string $rootNamespaceDir)
    {
        $this->autoloadFqClassNamePrefix = $rootNamespace . '\\';
        $this->autoloadFqClassNamePrefixLength = strlen($this->autoloadFqClassNamePrefix);
        $this->srcFilePathPrefix = $rootNamespaceDir . DIRECTORY_SEPARATOR;
    }

    public static function register(string $rootNamespace, string $rootNamespaceDir): void
    {
        spl_autoload_register((new self($rootNamespace, $rootNamespaceDir))->autoloadCodeForClass(...));
    }

    private function shouldAutoloadCodeForClass(string $fqClassName): bool
    {
        // does the class use the namespace prefix?
        return strncmp($this->autoloadFqClassNamePrefix, $fqClassName, $this->autoloadFqClassNamePrefixLength) == 0;
    }

    public function autoloadCodeForClass(string $fqClassName): void
    {
        // Example of $fqClassName: Elastic\OTel\Autoloader

        BootstrapStageLogger::logTrace("Entered with fqClassName: `$fqClassName'", __FILE__, __LINE__, __CLASS__, __FUNCTION__);

        if (!self::shouldAutoloadCodeForClass($fqClassName)) {
            BootstrapStageLogger::logTrace(
                "shouldAutoloadCodeForClass returned false. fqClassName: $fqClassName",
                __FILE__,
                __LINE__,
                __CLASS__,
                __FUNCTION__
            );
            return;
        }

        // get the relative class name
        $relativeClass = substr($fqClassName, $this->autoloadFqClassNamePrefixLength);
        $classSrcFileRelative = ((DIRECTORY_SEPARATOR === '\\')
            ? $relativeClass
            : str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)) . '.php';
        $classSrcFileAbsolute = $this->srcFilePathPrefix . $classSrcFileRelative;

        if (file_exists($classSrcFileAbsolute)) {
            BootstrapStageLogger::logTrace(
                "Before require `$classSrcFileAbsolute' ...",
                __FILE__,
                __LINE__,
                __CLASS__,
                __FUNCTION__
            );

            require $classSrcFileAbsolute;

            BootstrapStageLogger::logTrace(
                "After require `$classSrcFileAbsolute' ...",
                __FILE__,
                __LINE__,
                __CLASS__,
                __FUNCTION__
            );
        } else {
            BootstrapStageLogger::logTrace(
                "File with the code for class doesn't exist."
                    . " classSrcFile: `$classSrcFileAbsolute'. fqClassName: `$fqClassName'",
                __FILE__,
                __LINE__,
                __CLASS__,
                __FUNCTION__
            );
        }
    }
}
