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
use Elastic\OTel\Log\ElasticLogWriter;
use Elastic\OTel\HttpTransport\ElasticHttpTransportFactory;
use OpenTelemetry\SDK\SdkAutoloader;
use RuntimeException;
use Throwable;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * Called by the extension
 *
 * @noinspection PhpUnused, PhpMultipleClassDeclarationsInspection
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
     * @param string $elasticOTelNativePartVersion
     * @param int    $maxEnabledLogLevel
     * @param float  $requestInitStartTime
     *
     * @return bool
     */
    public static function bootstrap(string $elasticOTelNativePartVersion, int $maxEnabledLogLevel, float $requestInitStartTime): bool
    {
        require __DIR__ . DIRECTORY_SEPARATOR . 'BootstrapStageLogger.php';

        BootstrapStageLogger::configure($maxEnabledLogLevel, __DIR__, __NAMESPACE__);
        BootstrapStageLogger::logDebug(
            'Starting bootstrap sequence...'
            . "; elasticOTelNativePartVersion: $elasticOTelNativePartVersion" . "; maxEnabledLogLevel: $maxEnabledLogLevel" . "; requestInitStartTime: $requestInitStartTime",
            __FILE__,
            __LINE__,
            __CLASS__,
            __FUNCTION__
        );

        if (self::$singletonInstance !== null) {
            BootstrapStageLogger::logCritical(
                'bootstrap() is called even though singleton instance is already created (probably bootstrap() is called more than once)',
                __FILE__,
                __LINE__,
                __CLASS__,
                __FUNCTION__
            );
            return false;
        }

        try {
            require __DIR__ . DIRECTORY_SEPARATOR . 'Autoloader.php';
            Autoloader::register(__DIR__);

            InstrumentationBridge::singletonInstance()->bootstrap();
            self::prepareEnvForOTelSdk($elasticOTelNativePartVersion);
            self::registerAutoloader();
            self::registerAsyncTransportFactory();
            self::registerOtelLogWriter();

            /** @noinspection PhpInternalEntityUsedInspection */
            if (SdkAutoloader::isExcludedUrl()) {
                BootstrapStageLogger::logDebug('Url is excluded', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
                return false;
            }

            Traces\ElasticRootSpan::startRootSpan();

            self::$singletonInstance = new self();
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable($throwable, 'One of the steps in bootstrap sequence has thrown', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return false;
        }

        BootstrapStageLogger::logDebug('Successfully completed bootstrap sequence', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        return true;
    }

    private static function buildElasticOTelVersion(string $nativePartVersion): string
    {
        if ($nativePartVersion === PhpPartVersion::VALUE) {
            return $nativePartVersion;
        }

        BootstrapStageLogger::logWarning(
            'Native part and PHP part versions do not match. native part version: ' . $nativePartVersion . '; PHP part version: ' . PhpPartVersion::VALUE,
            __FILE__,
            __LINE__,
            __CLASS__,
            __FUNCTION__
        );
        return $nativePartVersion . '/' . PhpPartVersion::VALUE;
    }

    private static function isInDevMode(): bool
    {
        $modeIsDevEnvVarVal = getenv('ELASTIC_OTEL_PHP_DEV_INTERNAL_MODE_IS_DEV');
        if (is_string($modeIsDevEnvVarVal)) {
            /**
             * @var string[] $trueStringValues
             * @noinspection PhpRedundantVariableDocTypeInspection
             */
            static $trueStringValues = ['true', 'yes', 'on', '1'];
            foreach ($trueStringValues as $trueStringValue) {
                if (strcasecmp($modeIsDevEnvVarVal, $trueStringValue) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function prepareEnvForOTelAttributes(string $elasticOTelNativePartVersion): void
    {
        $envVarName = 'OTEL_RESOURCE_ATTRIBUTES';
        $envVarValueOnEntry = getenv($envVarName);
        $envVarValue = (is_string($envVarValueOnEntry) && strlen($envVarValueOnEntry) !== 0) ? ($envVarValueOnEntry . ',') : '';

        // https://opentelemetry.io/docs/specs/semconv/resource/#telemetry-distribution-experimental
        $envVarValue .= 'telemetry.distro.name=elastic,telemetry.distro.version=' . self::buildElasticOTelVersion($elasticOTelNativePartVersion);

        self::setEnvVar($envVarName, $envVarValue);
    }

    private static function setEnvVar(string $envVarName, string $envVarValue): void
    {
        if (!putenv($envVarName . '=' . $envVarValue)) {
            throw new RuntimeException('putenv returned false; $envVarName: ' . $envVarName . '; envVarValue: ' . $envVarValue);
        }
    }

    private static function prepareEnvForOTelSdk(string $elasticOTelNativePartVersion): void
    {
        self::setEnvVar('OTEL_PHP_AUTOLOAD_ENABLED', 'true');
        self::prepareEnvForOTelAttributes($elasticOTelNativePartVersion);
    }

    private static function registerAutoloader(): void
    {
        $vendorDir = ProdPhpDir::$fullPath . '/vendor' . (self::isInDevMode() ? '' : '_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION);
        $vendorAutoloadPhp = $vendorDir . '/autoload.php';
        if (!file_exists($vendorAutoloadPhp)) {
            throw new RuntimeException("File $vendorAutoloadPhp does not exist");
        }
        BootstrapStageLogger::logDebug('About to require ' . $vendorAutoloadPhp, __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        require $vendorAutoloadPhp;

        BootstrapStageLogger::logDebug('Finished successfully', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
    }

    private static function registerAsyncTransportFactory(): void
    {
        /**
         * elastic_otel_* functions are provided by the extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         */
        if (\elastic_otel_get_config_option_by_name('async_transport') === false) { // @phpstan-ignore function.notFound
            BootstrapStageLogger::logDebug('ELASTIC_OTEL_ASYNC_TRANSPORT set to false', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return;
        }

        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        \OpenTelemetry\SDK\Registry::registerTransportFactory('http', ElasticHttpTransportFactory::class, true);
    }

    private static function registerOtelLogWriter(): void
    {
        ElasticLogWriter::enableLogWriter();
    }

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function handleError(int $type, string $errorFilename, int $errorLineno, string $message): void
    {
        BootstrapStageLogger::logDebug(
            "Called with arguments: type: $type, errorFilename: $errorFilename, errorLineno: $errorLineno, message: $message",
            __FILE__,
            __LINE__,
            __CLASS__,
            __FUNCTION__
        );
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
