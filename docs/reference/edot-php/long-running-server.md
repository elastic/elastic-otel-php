---
navigation_title: Long-running PHP servers
description: Configure the Elastic Distribution of OpenTelemetry PHP for long-running PHP server frameworks such as Laravel Octane.
applies_to:
  stack:
  serverless:
    observability:
  product:
    edot_php: ga
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Long-running PHP servers

PHP frameworks such as **Laravel Octane** (with Swoole or RoadRunner) run PHP as a persistent server process rather than spawning a new process per request. This changes how EDOT PHP behaves in several important ways and requires specific configuration adjustments.

## How traditional PHP servers work

With **PHP-FPM** or **Apache mod_php**, each HTTP request maps to its own PHP process lifecycle:

1. PHP process starts — EDOT PHP bootstraps (OTel SDK initialized, auto-instrumentation hooks registered).
2. Request is handled — spans are created for the HTTP transaction and any instrumented calls (curl, PDO, and so on).
3. Response is sent — PHP shutdown functions run — spans are flushed and exported.
4. PHP process exits.

The **transaction span** (`OTEL_PHP_TRANSACTION_SPAN_ENABLED`) wraps exactly one HTTP request: it starts when the request arrives (web SAPI, `$_SERVER` populated) and ends when the process exits. This is the root span that all child spans (curl, DB queries, and so on) hang from.

## How long-running servers differ

With **Laravel Octane** (Swoole or RoadRunner), one PHP worker process handles many HTTP requests in a row without exiting between them:

1. PHP process starts — worker processes start with a fully initialized EDOT PHP (SDK, hooks, exporter).
2. Each HTTP request is dispatched to a worker — the worker's hooks fire and spans are created.
3. Response is sent — **PHP shutdown functions do NOT run** (the process continues).
4. Worker process exits only when the server is stopped (graceful stop).

Because the worker process is a **CLI process** (started with `php artisan octane:start`), the SAPI is always `cli`, not `fpm-fcgi` or `apache2handler`. EDOT PHP uses this to distinguish:

- `OTEL_PHP_TRANSACTION_SPAN_ENABLED` — root span for web SAPI (FPM/Apache). Not relevant here.
- `OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI` — root span for CLI processes. In a long-running server, this would wrap the **entire server lifetime** (from `octane:start` to `octane:stop`), not an individual request, which is not useful telemetry.

| Aspect | PHP-FPM / Apache | Long-running server (Octane) |
|---|---|---|
| SAPI | `fpm-fcgi` / `apache2handler` | `cli` |
| Process lifetime | One process per request | One worker process handles many requests |
| PHP shutdown functions | Run after every request | Run only when the worker process exits |
| EDOT PHP bootstrap | Runs per request | Runs once per worker process on startup |
| Transaction span (`_CLI`) | Not applicable | Would span the entire server lifetime |

## Recommended configuration

### Turn off the CLI transaction span

The auto root span (`OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI`) wraps the whole PHP process. In a long-running server, this means one span lasting until the server shuts down, which is not useful telemetry. Turn this setting off:

```bash
export OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI=false
```

:::{note}
`OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI` is CLI-specific and has no effect on web SAPI (FPM/Apache) deployments.
:::

### Turn off inferred spans

Inferred spans (stack-trace sampling) are designed for traditional request-based PHP. In a long-running server, the sampling runs continuously between requests, generating noise and consuming CPU. Turn inferred spans off:

```bash
export OTEL_PHP_INFERRED_SPANS_ENABLED=false
```

This is the default value, so it is only needed if you previously turned on inferred spans globally.

### Span processor and export latency

By default EDOT PHP uses `BatchSpanProcessor`, which accumulates spans in memory and exports them on a timer (the default is every 5 seconds). PHP is single-threaded, so the timer check runs only when a new span ends via `onEnd()` — there is no background tick. Always use a graceful stop (`php artisan octane:stop`, SIGTERM) so that workers can finish their current request, run PHP shutdown functions, and flush the exporter before exiting. A hard stop (`SIGKILL`) bypasses all this.

With graceful stop, spans buffered in memory are flushed before the process exits, provided the OTLP endpoint is reachable. However, `BatchSpanProcessor` introduces **export latency** proportional to how frequently requests arrive. On a low-traffic application, a span created at 10:00 can't appear in the collector until 10:05 (the next request finally triggers the timer check). For near-real-time visibility, use `SimpleSpanProcessor`:

```bash
export OTEL_PHP_TRACES_PROCESSOR=simple
```

Each span is pushed to the export queue immediately on `onEnd()`, regardless of traffic volume.

The native C++ transport (`HttpTransportAsync`) has its own internal queue and a persistent connection to the OTLP endpoint, so switching to `simple` does **not** mean one HTTP request per span. The PHP layer pushes to the C++ queue synchronously (a fast in-process operation), and the C++ layer batches and sends over the persistent connection independently.

## Complete example

The same configuration applies to both Swoole and RoadRunner:

```bash
export OTEL_SERVICE_NAME="my-laravel-octane-app"
export OTEL_EXPORTER_OTLP_ENDPOINT="http://localhost:4318"

# Long-running server adjustments
export OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI=false
export OTEL_PHP_INFERRED_SPANS_ENABLED=false
export OTEL_PHP_TRACES_PROCESSOR=simple

# Swoole
php artisan octane:start --server=swoole

# RoadRunner
php artisan octane:start --server=roadrunner
```

## How instrumentation works per server type

### Swoole

Swoole creates worker processes by forking the master PHP process. EDOT PHP bootstraps once in the master and the workers inherit the initialized state (hooks, SDK, exporter connection). Each HTTP request in a worker triggers the registered auto-instrumentation hooks (Laravel, curl, PDO, and so on) and spans are created under the worker's TracerProvider.

### RoadRunner

RoadRunner is a Go-based application server that manages PHP worker processes. Unlike Swoole, it does not fork — it spawns each PHP worker as a separate process. Therefore, each worker bootstraps EDOT PHP independently on startup. From the instrumentation perspective, the behavior is identical: the worker's SAPI is `cli`, it handles many requests without exiting between them, and the same configuration applies.

## BatchSpanProcessor schedule delay

If you want to keep `BatchSpanProcessor` but reduce export latency, lower the schedule delay:

```bash
export OTEL_BSP_SCHEDULE_DELAY=500   # ms, default is 5000
```

This only helps on applications with steady traffic — the timer fires on `onEnd()`, so a quieter application still experiences latency proportional to the gap between requests.
