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

use Closure;
use Elastic\OTel\Util\SingletonInstanceTrait;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 */
final class InstrumentationBridge
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use SingletonInstanceTrait;

    /**
     * @var array<string, array<string, array{?Closure, ?Closure}>>
     */
    public array $delayedHooksMapPerClass;

    public function bootstrap(): bool
    {
        /**
         * \elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $tryToHookRetVal = \elastic_otel_hook(
            null /* <- $class */,
            'spl_autoload_register',
            function (): array {
                $argsToUse = func_get_args();
                if (count($argsToUse) >= 1 && (is_callable($originalCallback = $argsToUse[0]))) {
                    $argsToUse[0] = function () use ($originalCallback) {
                        $argsPassedToCallback = func_get_args();
                        $originalCallback($argsPassedToCallback);
                        if (count($argsPassedToCallback) >= 1 && (is_string($class = $argsPassedToCallback[0]))) {
                            if (class_exists($class)) {
                                $this->onClassLoaded($class);
                            }
                        }
                    };
                }
                return $argsToUse;
            },
            function (): void {
                $passedArgs = func_get_args();
                if (count($passedArgs) >= 1 && $passedArgs[0] === false) {
                    BootstrapStageLogger::logError('Call to spl_autoload_register return false', __LINE__, __FUNCTION__);
                }
            }
        );
        if ($tryToHookRetVal) {
            return true;
        }

        require ProdPhpDir::$fullPath . 'OpenTelemetry' . DIRECTORY_SEPARATOR . 'Instrumentation' . DIRECTORY_SEPARATOR . 'hook.php';

        return true;
    }

    public function hook(?string $class, string $function, ?Closure $pre = null, ?Closure $post = null): bool
    {
        /**
         * \elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $tryToHookRetVal = \elastic_otel_hook($class, $function, $pre, $post);
        if ($tryToHookRetVal) {
            return true;
        }

        if ($class === null) {
            BootstrapStageLogger::logError('elastic_otel_hook return false. function: ' . $function, __LINE__, __FUNCTION__);
            return false;
        }

        if (class_exists($class)) {
            BootstrapStageLogger::logError('elastic_otel_hook return false. class: ' . $class . ' (class exists), function: ' . $function, __LINE__, __FUNCTION__);
            return false;
        }

        $this->addToDelayedHooks($class, $function, $pre, $post);
        return true;
    }

    private function addToDelayedHooks(string $class, string $function, ?Closure $pre = null, ?Closure $post = null): void
    {
        BootstrapStageLogger::logDebug('Adding to delayed hooks. class: ' . $class . ', function: ' . $function, __LINE__, __FUNCTION__);

        if (!array_key_exists($class, $this->delayedHooksMapPerClass)) {
            $this->delayedHooksMapPerClass[$class] = [];
        }
        $this->delayedHooksMapPerClass[$class][$function] = [$pre, $post];
    }

    private function onClassLoaded(string $class): void
    {
        BootstrapStageLogger::logTrace('Class loaded. class: ' . $class, __LINE__, __FUNCTION__);

        if (!array_key_exists($class, $this->delayedHooksMapPerClass)) {
            BootstrapStageLogger::logTrace('Class is not found in delayed hooks. class: ' . $class, __LINE__, __FUNCTION__);
            return;
        }

        foreach ($this->delayedHooksMapPerClass[$class] as $function => $prePostPairArr) {
            /**
             * \elastic_otel_* functions are provided by the extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            if(\elastic_otel_hook($class, $function, $prePostPairArr[0], $prePostPairArr[1])) {
                BootstrapStageLogger::logError('elastic_otel_hook return false. class: ' . $class . ' (class exists), function: ' . $function, __LINE__, __FUNCTION__);
            }
        }
    }
}
