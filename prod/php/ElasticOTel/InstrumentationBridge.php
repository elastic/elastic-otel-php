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
use Throwable;
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
     * @var array<array{string, string, ?Closure, ?Closure}>
     */
    public array $delayedHooks = [];

    private bool $enableDebugHooks;

    public function bootstrap(): void
    {
        self::elasticOTelHook(null, 'spl_autoload_register', null, Closure::fromCallable([$this, 'retryDelayedHooks']));

        require ProdPhpDir::$fullPath . DIRECTORY_SEPARATOR . 'OpenTelemetry' . DIRECTORY_SEPARATOR . 'Instrumentation' . DIRECTORY_SEPARATOR . 'hook.php';

        $this->enableDebugHooks = (bool)\elastic_otel_get_config_option_by_name('debug_php_hooks_enabled'); // @phpstan-ignore function.notFound

        BootstrapStageLogger::logDebug('Finished successfully', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
    }

    public function hook(?string $class, string $function, ?Closure $pre = null, ?Closure $post = null): bool
    {
        BootstrapStageLogger::logTrace('Entered. class: ' . $class .  ' function: ' . $function, __FILE__, __LINE__, __CLASS__, __FUNCTION__);

        if ($class !== null && !self::classOrInterfaceExists($class)) {
            $this->addToDelayedHooks($class, $function, $pre, $post);
            return true;
        }

        $success = self::elasticOTelHookNoThrow($class, $function, $pre, $post);

        if ($this->enableDebugHooks) {
            self::placeDebugHooks($class, $function);
        }

        return $success;
    }

    private function addToDelayedHooks(string $class, string $function, ?Closure $pre = null, ?Closure $post = null): void
    {
        BootstrapStageLogger::logTrace('Adding to delayed hooks. class: ' . $class . ', function: ' . $function, __FILE__, __LINE__, __CLASS__, __FUNCTION__);

        $this->delayedHooks[] = [$class, $function, $pre, $post];
    }

    private static function elasticOTelHook(?string $class, string $function, ?Closure $pre = null, ?Closure $post = null): void
    {
        $dbgClassAsString = BootstrapStageLogger::nullableToLog($class);
        BootstrapStageLogger::logTrace('Entered. class: ' . $dbgClassAsString . ', function: ' . $function, __FILE__, __LINE__, __CLASS__, __FUNCTION__);

        /**
         * \elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         */
        $retVal = \elastic_otel_hook($class, $function, $pre, $post); // @phpstan-ignore function.notFound
        if ($retVal) {
            BootstrapStageLogger::logTrace('Successfully hooked. class: ' . $dbgClassAsString . ', function: ' . $function, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return;
        }

        BootstrapStageLogger::logDebug('elastic_otel_hook returned false: ' . $dbgClassAsString . ', function: ' . $function, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
    }

    private static function elasticOTelHookNoThrow(?string $class, string $function, ?Closure $pre = null, ?Closure $post = null): bool
    {
        try {
            self::elasticOTelHook($class, $function, $pre, $post);
            return true;
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable($throwable, 'Call to elasticOTelHook has thrown', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return false;
        }
    }

    private function retryDelayedHooks(): void
    {
        $delayedHooksCount = count($this->delayedHooks);
        BootstrapStageLogger::logTrace('Entered. delayedHooks count: ' . $delayedHooksCount, __FILE__, __LINE__, __CLASS__, __FUNCTION__);

        if (count($this->delayedHooks) === 0) {
            return;
        }

        $delayedHooksToKeep = [];
        foreach ($this->delayedHooks as $delayedHookTuple) {
            $class = $delayedHookTuple[0];
            if (!self::classOrInterfaceExists($class)) {
                BootstrapStageLogger::logTrace('Class/Interface still does not exist - keeping delayed hook. class: ' . $class, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
                $delayedHooksToKeep[] = $delayedHookTuple;
                continue;
            }

            self::elasticOTelHook(...$delayedHookTuple);
        }

        $this->delayedHooks = $delayedHooksToKeep;
        BootstrapStageLogger::logTrace('Exiting... delayedHooks count: ' . count($this->delayedHooks), __FILE__, __LINE__, __CLASS__, __FUNCTION__);
    }

    private static function classOrInterfaceExists(string $classOrInterface): bool
    {
        return class_exists($classOrInterface) || interface_exists($classOrInterface);
    }

    private static function placeDebugHooks(?string $class, string $function): void
    {
        $func = '\'';
        if ($class) {
            $func = $class . '::';
        }
        $func .= $function . '\'';

        self::elasticOTelHookNoThrow($class, $function, function () use ($func) {
          /**
             * elastic_otel_* functions are provided by the extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             */
            \elastic_otel_log_feature( // @phpstan-ignore function.notFound
                0,
                Log\Level::DEBUG,
                Log\LogFeature::INSTRUMENTATION,
                'PRE HOOK',
                '',
                null,
                $func,
                ('pre-hook data: ' . var_export(func_get_args(), true))
            );
        }, function () use ($func) {
            /**
             * elastic_otel_* functions are provided by the extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             */
            \elastic_otel_log_feature( // @phpstan-ignore function.notFound
                0,
                Log\Level::DEBUG,
                Log\LogFeature::INSTRUMENTATION,
                'POST HOOK',
                '',
                null,
                $func,
                ('post-hook data: ' . var_export(func_get_args(), true))
            );
        });
    }
}
