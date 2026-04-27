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

/**
 * EDOT PHP Bootstrap Wrapper
 *
 * This file is loaded by the native extension as the bootstrap entry point.
 * It provides backward compatibility for ELASTIC_OTEL_* environment variables
 * by mapping them to the upstream OTEL_PHP_* equivalents, then delegates to
 * the upstream opentelemetry-php-distro bootstrap.
 *
 * The native extension's ElasticConfigProvider handles the env/INI alias
 * mapping for C++-side configuration. This file handles the PHP-side mapping
 * for env vars that the PHP SDK reads directly (not through the native config).
 */

// ── PHP-side environment variable compatibility ────────────────────────
// Map ELASTIC_OTEL_* env vars to OTEL_PHP_* equivalents for the PHP SDK.
// Only set the OTEL_PHP_* var if it's not already set (OTEL_PHP_* takes
// precedence if the user explicitly sets it).
$elasticToOtelEnvMap = [
    'ELASTIC_OTEL_ENABLED'                      => 'OTEL_PHP_ENABLED',
    'ELASTIC_OTEL_LOG_LEVEL'                     => 'OTEL_PHP_LOG_LEVEL',
    'ELASTIC_OTEL_LOG_FILE'                      => 'OTEL_PHP_LOG_FILE',
    'ELASTIC_OTEL_LOG_OTEL_WITH_CONTEXT'         => 'OTEL_PHP_LOG_OTEL_WITH_CONTEXT',
    'ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED'      => 'OTEL_PHP_TRANSACTION_SPAN_ENABLED',
    'ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI'  => 'OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI',
    'ELASTIC_OTEL_TRANSACTION_URL_GROUPS'         => 'OTEL_PHP_TRANSACTION_URL_GROUPS',
];

foreach ($elasticToOtelEnvMap as $elasticVar => $otelVar) {
    $elasticVal = getenv($elasticVar);
    if ($elasticVal !== false && getenv($otelVar) === false) {
        putenv("{$otelVar}={$elasticVal}");
    }
}
unset($elasticToOtelEnvMap, $elasticVar, $otelVar, $elasticVal);

// ── Delegate to upstream bootstrap ─────────────────────────────────────
// The upstream bootstrap_php_part.php handles:
//   - Scoper configuration and prefix resolution
//   - ProdPhpDir / VendorDir setup
//   - PhpPartFacade loading and bootstrap() call
//   - Autoloader registration, instrumentation bridge, root span, etc.

// In the installed package layout, both bootstrap_elastic.php and
// bootstrap_php_part.php live in the same directory (/opt/.../php/).
// In the development layout, upstream is in a submodule.
$upstreamBootstrap = __DIR__ . '/bootstrap_php_part.php';

if (!file_exists($upstreamBootstrap)) {
    // Development layout: upstream is a git submodule
    $upstreamBootstrap = dirname(__DIR__, 2) . '/upstream/prod/php/bootstrap_php_part.php';
}

require $upstreamBootstrap;
unset($upstreamBootstrap);

// ── Register EDOT vendor customizations ────────────────────────────────
// Must be called AFTER upstream bootstrap loads PhpPartFacade class
// but BEFORE the native extension calls PhpPartFacade::bootstrap().
//
// Upstream classes live in the scoped namespace (OTelDistroScoped\OpenTelemetry\Distro\*).
// EDOT code uses the non-scoped names (OpenTelemetry\Distro\*).
// Bridge the two with class_alias so PHP type checks pass.
$_edotScopePrefix = \OpenTelemetry\Distro\OTelDistroScoperConfig::PREFIX . '\\';
if (!interface_exists('OpenTelemetry\\Distro\\VendorCustomizationsInterface', false)) {
    class_alias($_edotScopePrefix . 'OpenTelemetry\\Distro\\VendorCustomizationsInterface', 'OpenTelemetry\\Distro\\VendorCustomizationsInterface');
}
if (!interface_exists('OpenTelemetry\\Distro\\RemoteConfigConsumerInterface', false)) {
    class_alias($_edotScopePrefix . 'OpenTelemetry\\Distro\\RemoteConfigConsumerInterface', 'OpenTelemetry\\Distro\\RemoteConfigConsumerInterface');
}
if (!class_exists('OpenTelemetry\\Distro\\PhpPartFacade', false)) {
    class_alias($_edotScopePrefix . 'OpenTelemetry\\Distro\\PhpPartFacade', 'OpenTelemetry\\Distro\\PhpPartFacade');
}
unset($_edotScopePrefix);

require __DIR__ . '/Elastic/OTel/ElasticVendorCustomizations.php';
require __DIR__ . '/Elastic/OTel/OpAmp/ElasticRemoteConfigParser.php';
require __DIR__ . '/Elastic/OTel/OpAmp/ElasticRemoteConfigConsumer.php';

\OpenTelemetry\Distro\PhpPartFacade::setVendorCustomizations(
    new \Elastic\OTel\ElasticVendorCustomizations()
);
\OpenTelemetry\Distro\PhpPartFacade::registerRemoteConfigConsumer(
    new \Elastic\OTel\OpAmp\ElasticRemoteConfigConsumer()
);

