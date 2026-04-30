---
navigation_title: Deprecations
description: Deprecations for Elastic Distribution of OpenTelemetry PHP.
applies_to:
  stack:
  serverless:
    observability:
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Elastic Distribution of OpenTelemetry PHP deprecations [edot-php-deprecations]

Over time, certain Elastic functionality becomes outdated and is replaced or removed. To help with the transition, Elastic deprecates functionality for a period before removal, giving you time to update your applications.

Review the deprecated functionality for Elastic Distribution of OpenTelemetry PHP. While deprecations have no immediate impact, we strongly encourage you update your implementation after you upgrade. To learn how to upgrade, check out [Upgrade](docs-content://deploy-manage/upgrade.md).

% ## Next version [edot-php-X.X.X-deprecations]

% Use the following template to add entries to this document.

% TEMPLATE START
% ::::{dropdown} Deprecation title
% Description of the deprecation.
% **Impact**<br> Impact of the deprecation.
% **Action**<br> Steps for mitigating impact.
% View [PR #](PR link).
% ::::
% TEMPLATE END

## 1.5.0 [edot-php-1.5.0-deprecations]

::::{dropdown} Deprecated `ELASTIC_OTEL_*` environment variables and `elastic_otel.*` INI settings
Starting in version 1.5.0, the `ELASTIC_OTEL_*` environment variables and `elastic_otel.*` php.ini settings are deprecated in favor of their `OTEL_PHP_*` and `opentelemetry_distro.*` equivalents.

The following environment variables are deprecated:

| Deprecated | Replacement |
| --- | --- |
| `ELASTIC_OTEL_ENABLED` | `OTEL_PHP_ENABLED` |
| `ELASTIC_OTEL_NATIVE_OTLP_SERIALIZER_ENABLED` | `OTEL_PHP_NATIVE_OTLP_SERIALIZER_ENABLED` |
| `ELASTIC_OTEL_LOG_LEVEL` | `OTEL_PHP_LOG_LEVEL` |
| `ELASTIC_OTEL_LOG_LEVEL_FILE` | `OTEL_PHP_LOG_LEVEL_FILE` |
| `ELASTIC_OTEL_LOG_LEVEL_STDERR` | `OTEL_PHP_LOG_LEVEL_STDERR` |
| `ELASTIC_OTEL_LOG_LEVEL_SYSLOG` | `OTEL_PHP_LOG_LEVEL_SYSLOG` |
| `ELASTIC_OTEL_LOG_FILE` | `OTEL_PHP_LOG_FILE` |
| `ELASTIC_OTEL_LOG_FEATURES` | `OTEL_PHP_LOG_FEATURES` |
| `ELASTIC_OTEL_ASYNC_TRANSPORT` | `OTEL_PHP_ASYNC_TRANSPORT` |
| `ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT` | `OTEL_PHP_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT` |
| `ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE` | `OTEL_PHP_MAX_SEND_QUEUE_SIZE` |
| `ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED` | `OTEL_PHP_TRANSACTION_SPAN_ENABLED` |
| `ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI` | `OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI` |
| `ELASTIC_OTEL_TRANSACTION_URL_GROUPS` | `OTEL_PHP_TRANSACTION_URL_GROUPS` |
| `ELASTIC_OTEL_INFERRED_SPANS_ENABLED` | `OTEL_PHP_INFERRED_SPANS_ENABLED` |
| `ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED` | `OTEL_PHP_INFERRED_SPANS_REDUCTION_ENABLED` |
| `ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED` | `OTEL_PHP_INFERRED_SPANS_STACKTRACE_ENABLED` |
| `ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL` | `OTEL_PHP_INFERRED_SPANS_SAMPLING_INTERVAL` |
| `ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION` | `OTEL_PHP_INFERRED_SPANS_MIN_DURATION` |
| `ELASTIC_OTEL_OPAMP_ENDPOINT` | `OTEL_PHP_OPAMP_ENDPOINT` |
| `ELASTIC_OTEL_OPAMP_HEADERS` | `OTEL_PHP_OPAMP_HEADERS` |
| `ELASTIC_OTEL_OPAMP_HEARTBEAT_INTERVAL` | `OTEL_PHP_OPAMP_HEARTBEAT_INTERVAL` |
| `ELASTIC_OTEL_OPAMP_SEND_TIMEOUT` | `OTEL_PHP_OPAMP_SEND_TIMEOUT` |
| `ELASTIC_OTEL_OPAMP_SEND_MAX_RETRIES` | `OTEL_PHP_OPAMP_SEND_MAX_RETRIES` |
| `ELASTIC_OTEL_OPAMP_SEND_RETRY_DELAY` | `OTEL_PHP_OPAMP_SEND_RETRY_DELAY` |
| `ELASTIC_OTEL_OPAMP_INSECURE` | `OTEL_PHP_OPAMP_INSECURE` |
| `ELASTIC_OTEL_OPAMP_CERTIFICATE` | `OTEL_PHP_OPAMP_CERTIFICATE` |
| `ELASTIC_OTEL_OPAMP_CLIENT_CERTIFICATE` | `OTEL_PHP_OPAMP_CLIENT_CERTIFICATE` |
| `ELASTIC_OTEL_OPAMP_CLIENT_KEY` | `OTEL_PHP_OPAMP_CLIENT_KEY` |
| `ELASTIC_OTEL_OPAMP_CLIENT_KEYPASS` | `OTEL_PHP_OPAMP_CLIENT_KEYPASS` |

The same applies to `elastic_otel.*` php.ini settings, which should be replaced with `opentelemetry_distro.*`.

**Impact**

The deprecated names continue to work as fallbacks. If both the deprecated and the new name are set, the new name (`OTEL_PHP_*` / `opentelemetry_distro.*`) takes precedence.

**Action** 

Update your configuration to use the `OTEL_PHP_*` environment variables and `opentelemetry_distro.*` php.ini settings. Refer to [Configuration](/reference/edot-php/configuration.md) for details.
::::