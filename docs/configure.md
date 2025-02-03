<!--
Goal of this doc:
Provide a complete reference of all available configuration options and where/how they can be set.
Any Elastic-specific configuration options are listed directly.
General OpenTelemetry configuration options are linked.
-->

# Configuration

Configure the Elastic Distribution of OpenTelemetry PHP (EDOT PHP) to send data to Elastic.

<!-- How users set configuration options -->
## Configuration method

<!-- Is this the right link to OpenTelemetry docs? -->
Configuration of the OpenTelemetry SDK should be performed through the mechanisms [documented on the OpenTelemetry website](https://opentelemetry.io/docs/zero-code/php#configuration). EDOT PHP is typically configured with `OTEL_*` environment variables defined by the OpenTelemetry spec. For example:

<!-- Include an example -->

<!-- List all available configuration options -->
## Configuration options

<!-- Is the distro an extension of the OTel PHP SDK? The agent? Or neither? -->
Because the Elastic Distribution of OpenTelemetry PHP is an extension of the OpenTelemetry PHP SDK, it supports:

* [OpenTelemetry configuration options](#opentelemetry-configuration-options)
* [Configuration options that are _only_ available in EDOT PHP](#configuration-options-that-are-only-available-in-edot-php)

### OpenTelemetry configuration options

EDOT PHP supports all configuration options listed in the [OpenTelemetry General SDK Configuration documentation](https://opentelemetry.io/docs/languages/sdk-configuration/general/) and [OpenTelemetry PHP SDK](https://opentelemetry.io/docs/languages/php).

<!--
Does EDOT PHP use different defaults for any of the general OTel configuration options?
If yes, what are the options? What's the general OTel default vs. the EDOT PHP default?

| Option | EDOT PHP default | OpenTelemetry default |
|---|---|---|
| <option> | <default> | <default> ([docs](<link to OTel docs>)) |
-->

### Configuration options that are _only_ available in EDOT PHP

In addition to general OpenTelemetry configuration options, there are two kinds of configuration options that are _only_ available in EDOT PHP.

<!-- This is true for the Java distro, is it also true of the PHP distro? -->
**Elastic-authored options that are not yet available upstream**

Additional `OTEL_` options that Elastic plans to contribute upstream to the OpenTelemetry PHP SDK, but are not yet available in the OpenTelemetry PHP SDK.

_Currently there are no additional `OTEL_` options waiting to be contributed upstream._

<!-- Are there any Elastic-specific configuration options? -->
**Elastic-specific options**

`ELASTIC_OTEL_` options that are specific to Elastic and will always live in EDOT PHP (in other words, they will _not_ be added upstream):

#### General configuration ####

| Option(s) | Default | Accepted values | Description |
|---|---|---|---|
|ELASTIC_OTEL_ENABLED|true|true or false|Enables the automatic bootstrapping of instrumentation code|

#### Asynchronous data sending configuration ####

| Option(s) | Default | Accepted values | Description |
|---|---|---|---|
|ELASTIC_OTEL_ASYNC_TRANSPORT|true| true or false | Use asynchronous (background) transfer of traces, metrics and logs. If false - brings back original OpenTelemetry SDK transfer modes|
|ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT| 30s | interger number with time duration. Set to 0 to disable the timeout. Optional units: ms (default), s, m | Timeout after which the asynchronous (background) transfer will interrupt data transmission during process termination|
|ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE|2MB| integer number with optional units: B, MB or GB | Set the maximum buffer size for asynchronous (background) transfer. It is set per worker process.|
|ELASTIC_OTEL_VERIFY_SERVER_CERT|true|true or false|Enables server certificate verification for asynchronous sending|

#### Logging configuration ####

| Option(s) | Default | Accepted values | Description |
|---|---|---|---|
|ELASTIC_OTEL_LOG_FILE||Filesystem path|Log file name. You can use the %p placeholder where the process ID will appear in the file name, and %t where the timestamp will appear. Please note that the PHP process must have write permissions for the specified path.|
|ELASTIC_OTEL_LOG_LEVEL_FILE|OFF|OFF, CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE|Log level for file sink. Set to OFF if you don't want to log to file.
|ELASTIC_OTEL_LOG_LEVEL_STDERR|OFF|OFF, CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE|Log level for the stderr sink. Set to OFF if you don't want to log to a file. This sink is recommended when running the application in a container.
|ELASTIC_OTEL_LOG_LEVEL_SYSLOG|OFF|OFF, CRITICAL, ERROR, WARNING, INFO, DEBUG, TRACE|Log level for file sink. Set to OFF if you don't want to log to file. This sink is recommended when you don't have write access to file system.
|ELASTIC_OTEL_LOG_FEATURES||Comma separated string with FEATURE=LEVEL pairs.<br>Supported features:<br>ALL, MODULE, REQUEST, TRANSPORT, BOOTSTRAP, HOOKS, INSTRUMENTATION|Allows selective setting of log level for features. For example, "ALL=info,TRANSPORT=trace" will result in all other features logging at the info level, while the TRANSPORT feature logs at the trace level. It should be noted that the appropriate log level must be set for the sink - for our example, this would be TRACE.

#### Transaction span configuration ####

| Option(s) | Default | Accepted values | Description |
|---|---|---|---|
|ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED|true|true or false|Enables automatic creation of transaction (root) spans for the webserver SAPI. The name of the span will correspond to the request method and path.|
|ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI|true|true or false|Enables automatic creation of transaction (root) spans for the CLI SAPI. The name of the span will correspond to the script name.|
|ELASTIC_OTEL_TRANSACTION_URL_GROUPS||Comma-separated list of wildcard expressions|Allows grouping multiple URL paths using wildcard expressions, such as `/user/*`. For example, `/user/Alice` and `/user/Bob` will be mapped to the transaction name `/user/*`.|
| <option> | <default value> | <description> |

#### Inferred spans configuration ####

| Option(s) | Default | Accepted values | Description |
|---|---|---|---|
|ELASTIC_OTEL_INFERRED_SPANS_ENABLED|false|true or false|Enables the inferred spans feature.|
|ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED|true|true or false|If enabled, reduces the number of spans by eliminating preceding frames with the same execution time.|
|ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED|true|true or false|If enabled, attaches a stack trace to the span metadata.|
|ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL|50ms|interger number with time duration. Optional units: ms (default), s, m. It can't be set to 0.|The frequency at which stack traces are gathered within a profiling session. The lower you set it, the more accurate the durations will be. This comes at the expense of higher overhead and more spans for potentially irrelevant operations. The minimal duration of a profiling-inferred span is the same as the value of this setting.|
|ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION|0|interger number with time duration. Optional units: ms (default), s, m. _Disabled if set to 0_.|The minimum duration of an inferred span. Note that the min duration is also implicitly set by the sampling interval. However, increasing the sampling interval also decreases the accuracy of the duration of inferred spans.|