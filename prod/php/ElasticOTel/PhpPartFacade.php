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

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Elastic\OTel;

use Elastic\OTel\HttpTransport\ElasticHttpTransportFactory;
use Elastic\OTel\InferredSpans\InferredSpans;
use Elastic\OTel\Log\ElasticLogWriter;
use Elastic\OTel\Util\HiddenConstructorTrait;
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\SdkAutoloader;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Version;
use RuntimeException;
use Throwable;

use function elastic_otel_get_config_option_by_name;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * Called by the extension
 */
final class PhpPartFacade
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use HiddenConstructorTrait;

    public static bool $wasBootstrapCalled = false;

    private static ?self $singletonInstance = null;
    private static bool $rootSpanEnded = false;
    private ?InferredSpans $inferredSpans = null;

    public const CONFIG_ENV_VAR_NAME_DEV_INTERNAL_MODE_IS_DEV = 'ELASTIC_OTEL_PHP_DEV_INTERNAL_MODE_IS_DEV';

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
        self::$wasBootstrapCalled = true;

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
            require __DIR__ . DIRECTORY_SEPARATOR . 'AutoloaderElasticOTelClasses.php';
            AutoloaderElasticOTelClasses::register(__DIR__);

            InstrumentationBridge::singletonInstance()->bootstrap();
            self::prepareForOTelSdk();
            self::registerAutoloaderForVendorDir();
            OverrideOTelSdkResourceAttributes::register($elasticOTelNativePartVersion);
            self::registerNativeOtlpSerializer();
            self::registerAsyncTransportFactory();
            self::registerOtelLogWriter();

            /** @noinspection PhpInternalEntityUsedInspection */
            if (SdkAutoloader::isExcludedUrl()) {
                BootstrapStageLogger::logDebug('Url is excluded', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
                return false;
            }

            Traces\ElasticRootSpan::startRootSpan(function () {
                PhpPartFacade::$rootSpanEnded = true;
                if (PhpPartFacade::$singletonInstance && PhpPartFacade::$singletonInstance->inferredSpans) {
                    PhpPartFacade::$singletonInstance->inferredSpans->shutdown();
                }
            });

            self::$singletonInstance = new self();

            RemoteConfigHandler::fetchAndApply();

            if (elastic_otel_get_config_option_by_name('inferred_spans_enabled')) {
                self::$singletonInstance->inferredSpans = new InferredSpans(
                    (bool)elastic_otel_get_config_option_by_name('inferred_spans_reduction_enabled'),
                    (bool)elastic_otel_get_config_option_by_name('inferred_spans_stacktrace_enabled'),
                    elastic_otel_get_config_option_by_name('inferred_spans_min_duration') // @phpstan-ignore argument.type
                );
            }
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable($throwable, 'One of the steps in bootstrap sequence has thrown', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return false;
        }

        BootstrapStageLogger::logDebug('Successfully completed bootstrap sequence', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        return true;
    }

    /**
     * Called by the extension
     *
     * @noinspection PhpUnused
     */
    public static function inferredSpans(int $durationMs, bool $internalFunction): bool
    {
        if (self::$singletonInstance === null) {
            BootstrapStageLogger::logDebug('Missing facade', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return true;
        }

        if (self::$singletonInstance->inferredSpans === null) {
            BootstrapStageLogger::logDebug('Missing inferred spans instance', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return true;
        }
        self::$singletonInstance->inferredSpans->captureStackTrace($durationMs, $internalFunction);

        return true;
    }

    private static function isInDevMode(): bool
    {
        $modeIsDevEnvVarVal = getenv(self::CONFIG_ENV_VAR_NAME_DEV_INTERNAL_MODE_IS_DEV);
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

    /**
     * @param non-empty-string $envVarName
     */
    private static function setEnvVar(string $envVarName, string $envVarValue): void
    {
        if (!putenv($envVarName . '=' . $envVarValue)) {
            throw new RuntimeException('putenv returned false; $envVarName: ' . $envVarName . '; envVarValue: ' . $envVarValue);
        }
    }

    private static function prepareForOTelSdk(): void
    {
        self::setEnvVar('OTEL_PHP_AUTOLOAD_ENABLED', 'true');
    }

    private static function registerAutoloaderForVendorDir(): void
    {
        $vendorDir = ProdPhpDir::$fullPath . DIRECTORY_SEPARATOR . (
            self::isInDevMode()
                ? ('..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor')
                : ('vendor_' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION)
            );
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
        if (elastic_otel_get_config_option_by_name('async_transport') === false) {
            BootstrapStageLogger::logDebug('ELASTIC_OTEL_ASYNC_TRANSPORT set to false', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
            return;
        }

        Registry::registerTransportFactory('http', ElasticHttpTransportFactory::class, true);
    }

    private static function registerOtelLogWriter(): void
    {
        ElasticLogWriter::enableLogWriter();
    }

    private static function registerNativeOtlpSerializer(): void
    {
        if (elastic_otel_get_config_option_by_name('native_otlp_serializer_enabled') === false) {
            BootstrapStageLogger::logDebug('ELASTIC_OTEL_NATIVE_OTLP_SERIALIZER_ENABLED set to false', __FILE__, __LINE__, __CLASS__, __FUNCTION__);
        } else {
            // Load classes such as \OpenTelemetry\Contrib\Otlp\SpanExporter to shadow the ones in SDK
            $otelOtlpDir = ProdPhpDir::$fullPath . DIRECTORY_SEPARATOR . 'OpenTelemetry' . DIRECTORY_SEPARATOR . 'Contrib' . DIRECTORY_SEPARATOR . 'Otlp';
            foreach (['SpanExporter', 'LogsExporter', 'MetricExporter'] as $exporter) {
                require $otelOtlpDir . DIRECTORY_SEPARATOR . $exporter . '.php';
            }
        }
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

    /**
     * Called by the extension
     *
     * @param array<mixed> $params
     *
     * @noinspection PhpUnused, PhpUnusedParameterInspection
     */
    public static function debugPreHook(mixed $object, array $params, ?string $class, string $function, ?string $filename, ?int $lineno): void
    {
        if (self::$rootSpanEnded) {
            return;
        }

        $tracer = Globals::tracerProvider()->getTracer(
            'co.elastic.edot.php.debug',
            null,
            Version::VERSION_1_25_0->url(),
        );

        $parent = Context::getCurrent();
        /** @noinspection PhpDeprecationInspection */
        $span = $tracer->spanBuilder($class ? $class . "::" . $function : $function) // @phpstan-ignore argument.type
                       ->setSpanKind(SpanKind::KIND_CLIENT)
                       ->setParent($parent)
                       ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                       ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                       ->setAttribute(TraceAttributes::CODE_FILE_PATH, $filename)
                       ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
                       ->setAttribute('call.arguments', print_r($params, true))
                       ->startSpan();

        $context = $span->storeInContext($parent);
        Context::storage()->attach($context);
    }

    /**
     * Called by the extension
     *
     * @param array<mixed> $params
     *
     * @noinspection PhpUnused, PhpUnusedParameterInspection
     */
    public static function debugPostHook(mixed $object, array $params, mixed $retval, ?Throwable $exception): void
    {
        if (self::$rootSpanEnded) {
            return;
        }

        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }

        $scope->detach();
        $span = Span::fromContext($scope->context());
        $span->setAttribute('call.return_value', print_r($retval, true));

        if ($exception) {
            /** @noinspection PhpDeprecationInspection */
            $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $span->end();
    }
}
