---
navigation_title: Breaking changes
description: Breaking changes for Elastic Distribution of OpenTelemetry PHP.
applies_to:
  stack:
  serverless:
    observability:
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Elastic Distribution of OpenTelemetry PHP breaking changes [edot-php-breaking-changes]

Breaking changes can impact your Elastic applications, potentially disrupting normal operations. Before you upgrade, carefully review the Elastic Distribution of OpenTelemetry PHP breaking changes and take the necessary steps to mitigate any issues.

% ## Next version [edot-php-X.X.X-breaking-changes]

% Use the following template to add entries to this document.

% TEMPLATE START
% ::::{dropdown} Title of breaking change
% Description of the breaking change.
% **Impact**<br> Impact of the breaking change.
% **Action**<br> Steps for mitigating impact.
% View [PR #](PR link).
% ::::
% TEMPLATE END

## Version 1.10.0 [edot-php-1.10.0-breaking-changes]

::::{dropdown} PHP 8.1 is no longer supported
EDOT PHP 1.10.0 supports PHP 8.2 to 8.5. The bundled `open-telemetry/sdk` 1.17, `api` 1.12, `context` 1.6, `exporter-otlp` 1.6, and `sdk-configuration` 0.10 packages require PHP 8.2.

**Impact**<br>
Applications that run on PHP 8.1 can't use EDOT PHP 1.10.0 or later.

**Action**<br>
Upgrade your applications to PHP 8.2 or later, or stay on EDOT PHP 1.9.x.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

::::{dropdown} File-based configuration requires file format 1.0
EDOT PHP follows `sdk-configuration` 0.10. The `OTEL_CONFIG_FILE` file must declare `file_format: "1.0"`. EDOT PHP rejects the `1.0-rc.*` and `0.x` formats. `OTEL_CONFIG_FILE` names exactly one file, and EDOT PHP no longer splits a comma-separated list. The schema changes of configuration 1.0 also apply. For example, `disabled` became `enabled` in the tracer, meter, and logger configurators, OTLP TLS settings moved under `tls`, `lowmemory` became `low_memory`, and the `zipkin` exporter was removed. An omitted `tracer_provider`, `meter_provider`, or `logger_provider` is now a no-op provider.

**Impact**<br>
Configuration files that use `file_format: "1.0-rc.2"` or an older schema stop working.

**Action**<br>
Update your configuration files to `file_format: "1.0"` and the 1.0 schema. Refer to the `sdk-configuration` 0.10.0 release notes for the full list of changes.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

::::{dropdown} `Configuration::getEnum()` returns lowercase values
Since `open-telemetry/sdk` 1.17, `Configuration::getEnum()` returns lowercase values. The SDK interprets enum configuration values and component names case-insensitively.

**Impact**<br>
Application code that compares the result of `Configuration::getEnum()` with uppercase or mixed-case strings no longer matches.

**Action**<br>
Compare the result with lowercase strings.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

::::{dropdown} `ext-protobuf` 4.31 or later is required for PHP-based OTLP serialization
`open-telemetry/exporter-otlp` requires `gen-otlp-protobuf` 1.11.1 (OTLP proto v1.11.1, up from v1.8.0) and `google/protobuf` 4.31. EDOT PHP serializes OTLP data natively by default, so this applies only when PHP code serializes the data: when you set `OTEL_PHP_NATIVE_OTLP_SERIALIZER_ENABLED=false`, or when your application uses its own `open-telemetry/exporter-otlp`.

**Impact**<br>
If the `ext-protobuf` PHP extension is installed and PHP code serializes OTLP data, versions earlier than 4.31 don't work.

**Action**<br>
Upgrade `ext-protobuf` to 4.31 or later, or remove the extension.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

::::{dropdown} Changes in the OTLP exporter classes
Code that extends or replaces the OTLP exporters must follow `open-telemetry/exporter-otlp` 1.5.0. `ProtobufSerializer` no longer defines the `SUPPORTED_CONTENT_TYPES` type alias. `MetricExporter` accepts an `AggregationTemporalitySelectorInterface` as its temporality argument.

**Impact**<br>
Custom classes that extend `ProtobufSerializer` or `MetricExporter`, or that use `SUPPORTED_CONTENT_TYPES`, fail.

**Action**<br>
Replace `SUPPORTED_CONTENT_TYPES` with `ContentTypes::*`. Pass an `AggregationTemporalitySelectorInterface` as the temporality argument of `MetricExporter`.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

::::{dropdown} Components register through SPI
The SDK registers exporter factories, transport factories, and propagators through SPI (`RegistrarInterface`) the first time the `Registry` is asked for one. `_register.php` files that Composer includes no longer do this. A component that you register with `Registry::register*()` before that first lookup wins over the built-in one. A component that you register with `$clobber` always wins. A package that registers a built-in name from its own autoload file now wins over the one in the SDK.

**Impact**<br>
Applications that register custom exporters, transport factories, or propagators with the built-in names can get a different component than before, depending on the registration order.

**Action**<br>
Allow the `tbachert/spi` Composer plugin in `config.allow-plugins` of your application's `composer.json` so the SPI registration takes effect. Without it, components register when their autoload files load, as before. Use `$clobber` if a component must always win. EDOT PHP builds its own bundled dependencies with the plugin.

View [upstream PR #172](https://github.com/open-telemetry/opentelemetry-php-distro/pull/172).
::::

## Version 1.2.0 [edot-php-1.2.0-breaking-changes]

::::{dropdown} Removal of ELASTIC_OTEL_VERIFY_SERVER_CERT (replaced by OTEL_EXPORTER_OTLP_INSECURE)
The environment variable ELASTIC_OTEL_VERIFY_SERVER_CERT has been removed and replaced by the standard OpenTelemetry variable OTEL_EXPORTER_OTLP_INSECURE.

**Impact**<br>
Any configuration still using ELASTIC_OTEL_VERIFY_SERVER_CERT is ignored. TLS server certificate verification now defaults to enabled (no change here) unless explicitly disabled via OTEL_EXPORTER_OTLP_INSECURE=true.

**Semantic change**<br>
Previous variable was affirmative (verify on true). New variable is negative (insecure on true):
- ELASTIC_OTEL_VERIFY_SERVER_CERT=true  → OTEL_EXPORTER_OTLP_INSECURE=false (secure; default)
- ELASTIC_OTEL_VERIFY_SERVER_CERT=false → OTEL_EXPORTER_OTLP_INSECURE=true (insecure; TLS verification disabled)

**Action**<br>
Remove ELASTIC_OTEL_VERIFY_SERVER_CERT from environments. For production leave OTEL_EXPORTER_OTLP_INSECURE unset or set to false. Only for local/testing disable verification:
```
OTEL_EXPORTER_OTLP_INSECURE=true
```

View PR [PR #300](https://github.com/elastic/elastic-otel-php/pull/300)
::::
