# Elastic Distribution of OpenTelemetry PHP

> [!WARNING]
> The Elastic Distribution of OpenTelemetry PHP is not yet recommended for production use. Functionality may be changed or removed in future releases. Alpha releases are not subject to the support SLA of official GA features.
>
> We welcome your feedback! You can reach us by [opening a GitHub issue](https://github.com/elastic/elastic-otel-php/issues) or starting a discussion thread on the [Elastic Discuss forum](https://discuss.elastic.co/tags/c/observability/apm/58/php).

<!--
Is the PHP distro built on top of the OTel PHP agent (https://opentelemetry.io/docs/zero-code/php/)?
Or the OTel PHP SDK (https://opentelemetry.io/docs/languages/php/)?
Both or neither?
-->
The Elastic Distribution of OpenTelemetry PHP (EDOT PHP) is a customized version of [OpenTelemetry for PHP](https://opentelemetry.io/docs/languages/php).
EDOT PHP makes it easier to get started using OpenTelemetry in your PHP applications through strictly OpenTelemetry native means, while also providing a smooth and rich out of the box experience with [Elastic Observability](https://www.elastic.co/observability). It's an explicit goal of this distribution to introduce **no new concepts** in addition to those defined by the wider OpenTelemetry community.

With EDOT PHP you have access to all the features of the OpenTelemetry PHP agent plus:

<!--
These are some examples from other distro docs.
Feel free to delete or edit these items or add new items to this list.
-->
* Access to SDK improvements and bug fixes contributed by the Elastic team _before_ the changes are available upstream in OpenTelemetry repositories.
* Access to optional features that can enhance OpenTelemetry data that is being sent to Elastic.
* Elastic-specific processors that ensure optimal compatibility when exporting OpenTelemetry signal data to an Elastic backend like an Elastic Observability deployment.
* Preconfigured collection of tracing and metrics signals, applying some opinionated defaults, such as which sources are collected by default.
* Ensuring that the OpenTelemetry protocol (OTLP) exporter is enabled by default.

**Ready to try out EDOT PHP?** Follow the step-by-step instructions in [Get started](./docs/get-started.md).

## Read the docs

* [Get started](./docs/get-started.md)
* [Configuration](./docs/configure.md)
