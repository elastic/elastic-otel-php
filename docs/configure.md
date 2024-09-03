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

<!--
| Option(s) | Default | Description |
|---|---|---|
| <option> | <default value> | <description> |
-->