---
navigation_title: Configuration
description: Configure the Elastic Distribution of OpenTelemetry PHP (EDOT PHP) to send data to Elastic.
applies_to:
  stack:
  serverless:
    observability:
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Configure the EDOT PHP SDK

Learn how to configure the {{edot}} PHP (EDOT PHP) to send data to Elastic.

Because the {{edot}} PHP is an extension of the OpenTelemetry PHP SDK, it supports:

* [OpenTelemetry configuration options](#opentelemetry-configuration-options)
* [EDOT PHP-specific configuration options](#edot-php-specific-configuration-options)

## Configuration method

You can configure the OpenTelemetry SDK through the mechanisms [documented on the OpenTelemetry website](https://opentelemetry.io/docs/zero-code/php#configuration). EDOT PHP is typically configured with `OTEL_*` environment variables defined by the OpenTelemetry spec. For example:

```bash
export OTEL_EXPORTER_OTLP_ENDPOINT="https://********.cloud.es.io:443/"
```

## OpenTelemetry configuration options

EDOT PHP supports all configuration options listed in the [OpenTelemetry General SDK Configuration documentation](https://opentelemetry.io/docs/languages/sdk-configuration/general/) and [OpenTelemetry PHP SDK](https://opentelemetry.io/docs/languages/php).

The most important OpenTelemetry options you should be aware of include:

| Option(s)                                                                                                                     | Default                 | Accepted values                                 | Description                                                                                                                                                                                                |
| ----------------------------------------------------------------------------------------------------------------------------- | ----------------------- | ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [OTEL_EXPORTER_OTLP_ENDPOINT](https://opentelemetry.io/docs/languages/sdk-configuration/otlp-exporter/#otel_exporter_otlp_endpoint) | `http://localhost:4318`       | URL                                             | Specifies the OTLP endpoint to which telemetry data should be sent.                                                                                                                    |
| [OTEL_EXPORTER_OTLP_HEADERS](https://opentelemetry.io/docs/languages/sdk-configuration/otlp-exporter/#otel_exporter_otlp_headers)   |                         | String of key-value pairs                       | Key-value pairs to be used as headers (for example, for authentication) when sending telemetry data through OTLP. Format: `key1=value1,key2=value2`.                                                                  |
| [OTEL_EXPORTER_OTLP_INSECURE](https://opentelemetry.io/docs/specs/otel/protocol/exporter/#configuration) | `false` | `true` or `false` | If `true`, disables TLS for the OTLP connection (plain HTTP). Use only for local testing; insecure in production. |
| [OTEL_EXPORTER_OTLP_CERTIFICATE](https://opentelemetry.io/docs/specs/otel/protocol/exporter/#configuration) |  | Filesystem path (PEM) | Filesystem path to a PEM-encoded CA certificate file or bundle. Must be a readable file; used to verify the OTLP server when TLS verification is enabled. |
| [OTEL_EXPORTER_OTLP_CLIENT_CERTIFICATE](https://opentelemetry.io/docs/specs/otel/protocol/exporter/#configuration) |  | Filesystem path (PEM) | Client certificate for mutual TLS (mTLS) with the OTLP endpoint. Must match the private key below. |
| [OTEL_EXPORTER_OTLP_CLIENT_KEY](https://opentelemetry.io/docs/specs/otel/protocol/exporter/#configuration) |  | Filesystem path (PEM) | Private key associated with `OTEL_EXPORTER_OTLP_CLIENT_CERTIFICATE`. Supports encrypted or unencrypted keys. |
| OTEL_EXPORTER_OTLP_CLIENT_KEYPASS |  | String (passphrase) | Passphrase for the encrypted private key. Don't set or leave empty if the key is not encrypted. |
| [OTEL_SERVICE_NAME](https://opentelemetry.io/docs/languages/sdk-configuration/general/#otel_service_name)                     | `unknown_service`       | String value                                    | Sets the value of the [service.name](https://opentelemetry.io/docs/specs/semconv/resource/#service) resource attribute.                                                                                    |
| [OTEL_RESOURCE_ATTRIBUTES](https://opentelemetry.io/docs/languages/sdk-configuration/general/#otel_resource_attributes)       |                         | String of key-value pairs                       | Key-value pairs to be used as resource attributes. See [Resource SDK](https://opentelemetry.io/docs/specs/otel/resource/sdk#specifying-resource-information-via-an-environment-variable) for more details. |
| [OTEL_TRACES_SAMPLER](https://opentelemetry.io/docs/languages/sdk-configuration/general/#otel_traces_sampler)                 | `parentbased_always_on` | `always_on`, `always_off`, `traceidratio`, etc. | Determines the sampler used for traces, which controls the amount of data collected and exported.                                                                                                          |
| [OTEL_TRACES_SAMPLER_ARG](https://opentelemetry.io/docs/languages/sdk-configuration/general/#otel_traces_sampler_arg)         |                         | String or number                                | Provides an argument to the configured traces sampler, such as the sampling ratio for `traceidratio` (e.g., `0.25` for 25% sampling).                                                                      |
| [OTEL_LOG_LEVEL](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/#general-sdk-configuration)                           | `info`                  | `error`, `warn`, `info`, `debug`                | Sets the verbosity level of the OpenTelemetry SDK’s internal logging. Useful for debugging configuration or troubleshooting instrumentation.                                                               |

For full configuration options of PHP SDK, see the official [OpenTelemetry PHP SDK Configuration documentation](https://opentelemetry.io/docs/languages/php/sdk/#configuration).

## Special considerations

EDOT PHP supports background data transmission (non-blocking export), but only when the exporter is set to `http/protobuf` (OTLP over HTTP), which is the default configuration.
If you change the exporter or the transport protocol, for example to gRPC or another format, telemetry data will be sent synchronously, potentially impacting request latency.

EDOT PHP also sets the `OTEL_PHP_AUTOLOAD_ENABLED` option to `true` by default. This turns on automatic instrumentation without requiring any changes to your application code.
Modifying this option will have no effect: EDOT will override it and enforce it as `true`.

## EDOT PHP-specific configuration options

In addition to general OpenTelemetry configuration options, EDOT PHP provides the following distribution-specific configuration options.

:::{note}
**Naming convention change:** Since EDOT PHP is now part of the contrib [opentelemetry-php-distro](https://github.com/open-telemetry/opentelemetry-php-distro) project, configuration options support two naming conventions:

| Convention | Environment variable prefix | php.ini prefix | Status |
|---|---|---|---|
| **OpenTelemetry (preferred)** | `OTEL_PHP_` | `opentelemetry_distro.` | Recommended |
| **Elastic (deprecated)** | `ELASTIC_OTEL_` | `elastic_otel.` | Deprecated — still functional for backward compatibility |

Both forms are fully functional. If both are set, the `OTEL_PHP_*` / `opentelemetry_distro.*` value takes precedence.

For example, `ELASTIC_OTEL_LOG_LEVEL` and `OTEL_PHP_LOG_LEVEL` are equivalent, but `OTEL_PHP_LOG_LEVEL` is preferred.
:::

Each option can be set using either an environment variable or the `php.ini` file:

::::{tab-set}

:::{tab-item} Environment variable (preferred)
```bash
export OTEL_PHP_ENABLED=true
```
:::

:::{tab-item} Environment variable (deprecated)
```bash
export ELASTIC_OTEL_ENABLED=true
```
:::

:::{tab-item} php.ini (preferred)
```ini
opentelemetry_distro.enabled=true
```
:::

:::{tab-item} php.ini (deprecated)
```ini
elastic_otel.enabled=true
```
:::

::::

### General configuration

| Option(s)            | Default | Accepted values | Description                                                 |
| -------------------- | ------- | --------------- | ----------------------------------------------------------- |
| OTEL_PHP_ENABLED | `true`    | `true` or `false`   | Activates the automatic bootstrapping of instrumentation code |
| OTEL_PHP_NATIVE_OTLP_SERIALIZER_ENABLED   | `true`    | `true` or `false`   | Activates the native built-in OTLP Protobuf serializer for maximum performance |

_Deprecated aliases: `ELASTIC_OTEL_ENABLED`, `ELASTIC_OTEL_NATIVE_OTLP_SERIALIZER_ENABLED`_

### Asynchronous data sending configuration

| Option(s)                                     | Default | Accepted values                                                                                         | Description                                                                                                                          |
| --------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| OTEL_PHP_ASYNC_TRANSPORT                  | `true`    | `true` or `false`                                                                                           | Use asynchronous (background) transfer of traces, metrics and logs. If false, reverts to the original OpenTelemetry SDK transfer modes |
| OTEL_PHP_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT | `30s`     | Integer number with time duration. Set to 0 to turn off the timeout. Optional units: ms (default), s, m | Timeout after which the asynchronous (background) transfer will interrupt data transmission during process termination               |
| OTEL_PHP_MAX_SEND_QUEUE_SIZE              | `2MB`     | integer number with optional units: `B`, `MB` or `GB`                                                         | Set the maximum buffer size for asynchronous (background) transfer. It is set per worker process.                                    |

_Deprecated aliases: `ELASTIC_OTEL_ASYNC_TRANSPORT`, `ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT`, `ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE`_

### Logging configuration

| Option(s)                     | Default | Accepted values                                                                                                                               | Description                                                                                                                                                                                                                                                                                                                      |
| ----------------------------- | ------- | --------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| OTEL_PHP_LOG_LEVEL        | `OFF`     | `OFF`, `CRITICAL`, `ERROR`, `WARNING`, `INFO`, `DEBUG`, `TRACE`                                                                                             | Sets the log level for all sinks at once. Individual sink levels (`LOG_LEVEL_FILE`, `LOG_LEVEL_STDERR`, `LOG_LEVEL_SYSLOG`) override this value if set.                                                                                                                                                                          |
| OTEL_PHP_LOG_FILE         |         | Filesystem path                                                                                                                               | Log file name. You can use the %p placeholder where the process ID will appear in the file name, and %t where the timestamp will appear. The PHP process must have write permissions for the specified path.                                                                                   |
| OTEL_PHP_LOG_LEVEL_FILE   | `OFF`     | `OFF`, `CRITICAL`, `ERROR`, `WARNING`, `INFO`, `DEBUG`, `TRACE`                                                                                             | Log level for file sink. Set to OFF if you don't want to log to file.                                                                                                                                                                                                                                                            |
| OTEL_PHP_LOG_LEVEL_STDERR | `OFF`     | `OFF`, `CRITICAL`, `ERROR`, `WARNING`, `INFO`, `DEBUG`, `TRACE`                                                                                             | Log level for the stderr sink. Set to OFF if you don't want to log to a stderr. This sink is recommended when running the application in a container.                                                                                                                                                                              |
| OTEL_PHP_LOG_LEVEL_SYSLOG | `OFF`     | `OFF`, `CRITICAL`, `ERROR`, `WARNING`, `INFO`, `DEBUG`, `TRACE`                                                                                             | Log level for syslog sink. Set to OFF if you don't want to log to syslog. This sink is recommended when you don't have write access to file system.                                                                                                                                                                                  |
| OTEL_PHP_LOG_FEATURES     |         | Comma separated string with `FEATURE=LEVEL` pairs.<br>Supported features:<br>`ALL`, `MODULE`, `REQUEST`, `TRANSPORT`, `BOOTSTRAP`, `HOOKS`, `INSTRUMENTATION` | Allows selective setting of log level for features. For example, "ALL=info,TRANSPORT=trace" will result in all other features logging at the info level, while the `TRANSPORT` feature logs at the trace level. It should be noted that the appropriate log level must be set for the sink. In the previous example, this would be `TRACE`. |

_Deprecated aliases: `ELASTIC_OTEL_LOG_LEVEL`, `ELASTIC_OTEL_LOG_FILE`, `ELASTIC_OTEL_LOG_LEVEL_FILE`, `ELASTIC_OTEL_LOG_LEVEL_STDERR`, `ELASTIC_OTEL_LOG_LEVEL_SYSLOG`, `ELASTIC_OTEL_LOG_FEATURES`_

### Transaction span configuration

| Option(s)                                 | Default         | Accepted values                              | Description                                                                                                                                                                    |
| ----------------------------------------- | --------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| OTEL_PHP_TRANSACTION_SPAN_ENABLED     | `true`            | `true` or `false`                                | Activates automatic creation of transaction (root) spans for the webserver SAPI. The name of the span will correspond to the request method and path.                            |
| OTEL_PHP_TRANSACTION_SPAN_ENABLED_CLI | `true`            | `true` or `false`                                | Activates automatic creation of transaction (root) spans for the CLI SAPI. The name of the span will correspond to the script name.                                              |
| OTEL_PHP_TRANSACTION_URL_GROUPS       |                 | Comma-separated list of wildcard expressions | Allows grouping multiple URL paths using wildcard expressions, such as `/user/*`. For example, `/user/Alice` and `/user/Bob` will be mapped to the transaction name `/user/*`. |

_Deprecated aliases: `ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED`, `ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI`, `ELASTIC_OTEL_TRANSACTION_URL_GROUPS`_

### Inferred spans configuration

| Option(s)                                      | Default | Accepted values                                                                                 | Description                                                                                                                                                                                                                                                                                                                                |
| ---------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| OTEL_PHP_INFERRED_SPANS_ENABLED            | `false`   | `true` or `false`                                                                                   | Activates the inferred spans feature.                                                                                                                                                                                                                                                                                                        |
| OTEL_PHP_INFERRED_SPANS_REDUCTION_ENABLED  | `true`    | `true` or `false`                                                                                   | When active, reduces the number of spans by eliminating preceding frames with the same execution time.                                                                                                                                                                                                                                      |
| OTEL_PHP_INFERRED_SPANS_STACKTRACE_ENABLED | `true`    | `true` or `false`                                                                                   | When active, attaches a stack trace to the span metadata.                                                                                                                                                                                                                                                                                   |
| OTEL_PHP_INFERRED_SPANS_SAMPLING_INTERVAL  | 50ms    | Integer number with time duration. Optional units: ms (default), s, m. It can't be set to 0.   | The frequency at which stack traces are gathered within a profiling session. The lower you set it, the more accurate the durations will be. This comes at the expense of higher overhead and more spans for potentially irrelevant operations. The minimal duration of a profiling-inferred span is the same as the value of this setting. |
| OTEL_PHP_INFERRED_SPANS_MIN_DURATION       | 0       | Integer number with time duration. Optional units: ms (default), s, m. _Deactivated when set to 0_. | The minimum duration of an inferred span. Note that the min duration is also implicitly set by the sampling interval. However, increasing the sampling interval also decreases the accuracy of the duration of inferred spans.                                                                                                             |

_Deprecated aliases: `ELASTIC_OTEL_INFERRED_SPANS_ENABLED`, `ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED`, `ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED`, `ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL`, `ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION`_

### Central configuration

The following settings control Central configuration management through OpAMP.

| Option(s)                             | Default                        | Accepted values                                                                               | Description                                                                                                                                                                 |
| ------------------------------------- | ------------------------------ | --------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| OTEL_PHP_OPAMP_ENDPOINT           |              | Valid HTTP or HTTPS URL.                                          | The HTTP or HTTPS endpoint of the OpAMP server. Required to activate Central configuration management. For example, `http://localhost:4320/v1/opamp`. Endpoint must always end with `/v1/opamp`.                            |
| OTEL_PHP_OPAMP_HEADERS            | -                              | Comma-separated key-value pairs. For example, `Authorization=Bearer xxxxxx,UserData=abc`            | Custom HTTP headers to send with the OpAMP connection request. Use key-value pairs separated by commas.                                                               |
| OTEL_PHP_OPAMP_HEARTBEAT_INTERVAL | 30s                            | Integer number with time duration. Optional units: ms (default), s, m. It can't be set to 0. | The interval between heartbeat messages sent to the OpAMP server. This also determines how often the agent will poll for updated configuration, if available.               |
| OTEL_PHP_OPAMP_SEND_TIMEOUT       | 10s                            | Integer number with time duration. Optional units: ms (default), s, m. It can't be set to 0. | Timeout duration for sending messages to the OpAMP server.                                                                                                                   |
| OTEL_PHP_OPAMP_SEND_MAX_RETRIES   | 3                              | Integer ≥ 0                                                                                   | Maximum number of retry attempts for failed message sends.                                                                                                                  |
| OTEL_PHP_OPAMP_SEND_RETRY_DELAY   | 10s                            | Integer number with time duration. Optional units: ms (default), s, m. It can't be set to 0. | Time to wait between retries of failed sends.                                                                                                                                |
| OTEL_PHP_OPAMP_INSECURE           | false                          | `true` or `false` | If `true`, turns off TLS server certificate and hostname verification for the OpAMP HTTPS endpoint (analogous to insecure mode in OTLP exporters). Use ONLY for local testing; leaves the connection vulnerable to MITM. |
| OTEL_PHP_OPAMP_CERTIFICATE        |                                | Filesystem path (PEM bundle) | Filesystem path to a PEM-encoded CA certificate file or bundle. Must be a readable file; used to verify the OpAMP server when TLS verification is turned on (same intent as custom root certificates for OTLP). |
| OTEL_PHP_OPAMP_CLIENT_CERTIFICATE |                                | Filesystem path (PEM) | Path to the client certificate for mutual TLS authentication to the OpAMP server (similar to OTLP mTLS client cert). Must match the private key below. |
| OTEL_PHP_OPAMP_CLIENT_KEY         |                                | Filesystem path (PEM) | Path to the unencrypted or encrypted private key associated with `OTEL_PHP_OPAMP_CLIENT_CERTIFICATE`. Required for mTLS. |
| OTEL_PHP_OPAMP_CLIENT_KEYPASS     |                                | String (passphrase) | Passphrase for the encrypted private key (if the key is protected). Don't set or leave empty if the key is not encrypted. |

_Deprecated aliases: `ELASTIC_OTEL_OPAMP_ENDPOINT`, `ELASTIC_OTEL_OPAMP_HEADERS`, `ELASTIC_OTEL_OPAMP_HEARTBEAT_INTERVAL`, `ELASTIC_OTEL_OPAMP_SEND_TIMEOUT`, `ELASTIC_OTEL_OPAMP_SEND_MAX_RETRIES`, `ELASTIC_OTEL_OPAMP_SEND_RETRY_DELAY`, `ELASTIC_OTEL_OPAMP_INSECURE`, `ELASTIC_OTEL_OPAMP_CERTIFICATE`, `ELASTIC_OTEL_OPAMP_CLIENT_CERTIFICATE`, `ELASTIC_OTEL_OPAMP_CLIENT_KEY`, `ELASTIC_OTEL_OPAMP_CLIENT_KEYPASS`_


#### Central configuration settings

You can modify the following settings for EDOT PHP through APM Agent Central Configuration

| Setting                            | Central configuration name      | Type    | Versions                                                               |
| ---------------------------------- | ------------------------------- | ------- |------------------------------------------------------------------------|
| Turn off all instrumentations      | deactivate_all_instrumentations | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.4.0` |
| Turn off selected instrumentations | deactivate_instrumentations     | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.4.0` |
| Logging level                      | logging_level                   | Dynamic | {applies_to}`stack: preview 9.1` {applies_to}`edot_php: preview 1.1.0` |
| Sampling rate                      | sampling_rate                   | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.2.0` |
| Turn off sending logs              | send_logs                       | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.4.0` |
| Turn off sending metrics           | send_metrics                    | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.4.0` |
| Turn off sending traces            | send_traces                     | Dynamic | {applies_to}`stack: preview 9.3` {applies_to}`edot_php: preview 1.4.0` |

Dynamic settings can be changed without having to restart the application or webserver process.

:::{note}
:applies_to: {"stack": "ga 9.2"}
Version 9.2 and later of the {{product.elastic-stack}} includes an
[Advanced configuration section](opentelemetry://reference/central-configuration.md#advanced-configuration)
that allows you to define custom configuration options as key-value pairs.

For example, you can configure the `sampling_rate` option for {{product.elastic-stack}} 9.2,
as long as EDOT PHP is on version 1.2.0 or later, even if `sampling_rate` applies to 9.3 and later.
:::

## Prevent logs export

To prevent logs from being exported, set `OTEL_LOGS_EXPORTER` to `none`. However, application logs might still be gathered and exported by the Collector through the `filelog` receiver.

To prevent application logs from being collected and exported by the Collector, refer to [Exclude paths from logs collection](elastic-agent://reference/edot-collector/config/configure-logs-collection.md#exclude-logs-paths).
