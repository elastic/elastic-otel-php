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

use Elastic\OTel\Util\HiddenConstructorTrait;
use Throwable;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * Called by the extension
 *
 * @noinspection PhpUnused
 */
final class PhpPartFacade
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use HiddenConstructorTrait;

    private static ?self $singletonInstance = null;

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     *
     * @param int   $maxEnabledLogLevel
     * @param float $requestInitStartTime
     *
     * @return bool
     */
    public static function bootstrap(int $maxEnabledLogLevel, float $requestInitStartTime): bool
    {
        require __DIR__ . DIRECTORY_SEPARATOR . 'BootstrapStageLogger.php';

        BootstrapStageLogger::configure($maxEnabledLogLevel);
        BootstrapStageLogger::logDebug(
            'Starting bootstrap sequence...' . "; maxEnabledLogLevel: $maxEnabledLogLevel" . "; requestInitStartTime: $requestInitStartTime",
            __LINE__,
            __FUNCTION__
        );

        if (self::$singletonInstance !== null) {
            BootstrapStageLogger::logCritical(
                'bootstrap() is called even though singleton instance is already created'
                . ' (probably bootstrap() is called more than once)',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        try {
            if (!self::registerAutoloader()) {
                return false;
            }
            self::$singletonInstance = new self();
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'One of the steps in bootstrap sequence let a throwable escape',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        BootstrapStageLogger::logDebug('Successfully completed bootstrap sequence', __LINE__, __FUNCTION__);
        return true;
    }

    private static function isInDevMode(): bool
    {
        $modeIsDevEnvVarVal = getenv('ELASTIC_OTEL_PHP_DEV_INTERNAL_MODE_IS_DEV');
        if (is_string($modeIsDevEnvVarVal)) {
            static $trueStringValues = ['true', 'yes', 'on', '1'];
            foreach ($trueStringValues as $trueStringValue) {
                if (strcasecmp($modeIsDevEnvVarVal, $trueStringValue) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function registerAutoloader(): bool
    {
        $vendorDir = ProdPhpDir::$fullPath . __DIR__ . 'vendor' . (self::isInDevMode() ? '' : '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION);
        $vendorAutoloadPhp = $vendorDir . __DIR__ . 'autoload.php';
        if (!file_exists($vendorAutoloadPhp)) {
            BootstrapStageLogger::logCritical("File $vendorAutoloadPhp does not exist", __LINE__, __FUNCTION__);
            return false;
        }
        require $vendorAutoloadPhp;
        return true;
    }

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function handleError(int $type, string $errorFilename, int $errorLineno, string $message)
    {
        BootstrapStageLogger::logDebug("Called with arguments: type: $type, errorFilename: $errorFilename, errorLineno: $errorLineno, message: $message", __LINE__, __FUNCTION__);
    }

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function shutdown(): void
    {
        self::$singletonInstance = null;
    }
}
