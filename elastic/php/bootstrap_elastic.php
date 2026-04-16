<?php

/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * Elasticsearch B.V. licenses this file to you under the Apache License,
 * Version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
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
    'ELASTIC_OTEL_ENABLED'         => 'OTEL_PHP_ENABLED',
    'ELASTIC_OTEL_LOG_LEVEL'       => 'OTEL_PHP_LOG_LEVEL',
    'ELASTIC_OTEL_LOG_FILE'        => 'OTEL_PHP_LOG_FILE',
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
