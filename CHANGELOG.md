
# Elastic Distribution for OpenTelemetry PHP Change Log

## v0.3.0

### What's new

- Added support for PHP 8.4 ([#130](https://github.com/elastic/elastic-otel-php/issues/130)) (PR [#132](https://github.com/elastic/elastic-otel-php/pull/132))
- Drop PHP 8.0 support due to end of life and lack of security updates ([#128](https://github.com/elastic/elastic-otel-php/issues/128)) (PR [#129](https://github.com/elastic/elastic-otel-php/pull/129))
- Automatic transaction span with configurable grouping ([#125](https://github.com/elastic/elastic-otel-php/issues/125))  (PR [#126](https://github.com/elastic/elastic-otel-php/pull/126))
- Enabled curl auto instrumentation ([#121](https://github.com/elastic/elastic-otel-php/issues/121)) (PR [#121](https://github.com/elastic/elastic-otel-php/pull/121))
- Enabled MySQLi instrumentation ([#135](https://github.com/elastic/elastic-otel-php/issues/135)) (PR [#145](https://github.com/elastic/elastic-otel-php/pull/145))
- Debug mode to instrument all user functions ([#144](https://github.com/elastic/elastic-otel-php/issues/144)) (PR [#144](https://github.com/elastic/elastic-otel-php/pull/144))
- Selective logging level per feature ([#103](https://github.com/elastic/elastic-otel-php/issues/103)) (PR [#112](https://github.com/elastic/elastic-otel-php/pull/112))
- Forced OTLP Exporter to send customized EDOT User-Agent header ([#123](https://github.com/elastic/elastic-otel-php/issues/123))  (PR [#133](https://github.com/elastic/elastic-otel-php/pull/133))

### Technical news

- Added static check and GitHub workflow for it ([#137](https://github.com/elastic/elastic-otel-php/issues/137)) (PR [#137](https://github.com/elastic/elastic-otel-php/pull/137))
- Autoloader for Elastic classes ([#115](https://github.com/elastic/elastic-otel-php/issues/115)), Use EDOT logging in OTel logging ([#116](https://github.com/elastic/elastic-otel-php/issues/116)) (PR [#117](https://github.com/elastic/elastic-otel-php/pull/117))
- Implemented automatic github release notes generator and changlog helper script ([#122](https://github.com/elastic/elastic-otel-php/issues/122)) (PR [#127](https://github.com/elastic/elastic-otel-php/pull/127))
- Moved ignore platform requirements from build script to composer file. ([#143](https://github.com/elastic/elastic-otel-php/issues/143)) (PR [#143](https://github.com/elastic/elastic-otel-php/pull/143))
- Adapted code to assume .php.template files converted to .php ([#138](https://github.com/elastic/elastic-otel-php/issues/138)) (PR [#138](https://github.com/elastic/elastic-otel-php/pull/138))
- Switch ARM64 build from qemu to native runner ([#108](https://github.com/elastic/elastic-otel-php/issues/108))  (PR [#111](https://github.com/elastic/elastic-otel-php/pull/111))
- Update toolset to latest gcc and conan ([#108](https://github.com/elastic/elastic-otel-php/issues/108)) (PR [#110](https://github.com/elastic/elastic-otel-php/pull/110))

### Bug fixes
- Don't throw exception if instrumented function is missing ([#142](https://github.com/elastic/elastic-otel-php/issues/142)) (PR [#142](https://github.com/elastic/elastic-otel-php/pull/142))


## v0.2.0

### What's new

- Asynchronous (background) transport for traces, logs and metrics ([#101](https://github.com/elastic/elastic-otel-php/issues/101)) (PR [#102](https://github.com/elastic/elastic-otel-php/pull/102))
- Timeout after which the asynchronous (background) transfer will interrupt data transmission during process termination ([#106](https://github.com/elastic/elastic-otel-php/issues/106)) (PR [#107](https://github.com/elastic/elastic-otel-php/pull/107))
- Improved documentation

## v0.1.0

- Initial alpha release
