<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace OpenTelemetry\Instrumentation;

use Closure;
use Elastic\OTel\InstrumentationBridge;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * Called by the OTel instrumentations
 *
 * @noinspection PhpUnused
 */

/**
 * @param string|null $class The hooked function's class. Null for a global/built-in function.
 * @param string $function The hooked function's name.
 * @param Closure|null $pre function($class, array $params, string $class, string $function, ?string $filename, ?int $lineno): $params
 *        You may optionally return modified parameters.
 * @param Closure|null $post function($class, array $params, $returnValue, ?Throwable $exception): $returnValue
 *        You may optionally return modified return value.
 * @return bool Whether the observer was successfully added
 *
 * @see https://github.com/open-telemetry/opentelemetry-php-instrumentation
 */
function hook(
    string|null $class,
    string $function,
    ?Closure $pre = null,
    ?Closure $post = null,
): bool {
    return InstrumentationBridge::singletonInstance()->hook($class, $function, $pre, $post);
}
