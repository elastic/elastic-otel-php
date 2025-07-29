---
navigation_title: EDOT PHP
description: Release notes for Elastic Distribution of OpenTelemetry PHP.
applies_to:
  stack:
  serverless:
    observability:
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Elastic Distribution of OpenTelemetry PHP release notes [edot-php-release-notes]

Review the changes, fixes, and more in each version of Elastic Distribution of OpenTelemetry PHP.

To check for security updates, go to [Security announcements for the Elastic stack](https://discuss.elastic.co/c/announcements/security-announcements/31).

% Release notes include only features, enhancements, and fixes. Add breaking changes, deprecations, and known issues to the applicable release notes sections.

% ## version.next [edot-php-X.X.X-release-notes]

% ### Features and enhancements [edot-php-X.X.X-features-enhancements]
% *

% ### Fixes [edot-php-X.X.X-fixes]
% *

## 1.1.1 [edot-php-1.1.1-release-notes]

### Fixes [edot-php-1.1.1-fixes]

- Controlling the OpAMP activation using the ELASTIC_OTEL_OPAMP_ENDPOINT environment variable ([#245](https://github.com/elastic/elastic-otel-php/issues/245)) (PR [#245](https://github.com/elastic/elastic-otel-php/pull/245))

## 1.1.0 [edot-php-1.1.0-release-notes]

### Features and enhancements [edot-php-1.1.0-features-enhancements]

- OpAMP remote configuration support (Technical preview) (PR [#243](https://github.com/elastic/elastic-otel-php/pull/243))
- Updated opentelemetry-auto-pdo package to 0.1.1  (PR [#233](https://github.com/elastic/elastic-otel-php/pull/233))
- Updated open-telemetry/sdk (PR [#235](https://github.com/elastic/elastic-otel-php/pull/235))
- Added component test for MySQLi auto-instrumentation (PR [#181](https://github.com/elastic/elastic-otel-php/pull/181))

### Other [edot-php-1.1.0-other]

- Enable std::format in logger (PR [#207](https://github.com/elastic/elastic-otel-php/pull/207))
- Added checks to ensure OTLP exporters native implementation is in sync with upstream PHP implementation (PR [#201](https://github.com/elastic/elastic-otel-php/pull/201))
- AutoZval phpt tests ([#202](https://github.com/elastic/elastic-otel-php/issues/202)) (PR [#203](https://github.com/elastic/elastic-otel-php/pull/203))
- Removed direct loading of opcache.so in phpt tests (PR [#237](https://github.com/elastic/elastic-otel-php/pull/237))
- Updated dev packages  (PR [#230](https://github.com/elastic/elastic-otel-php/pull/230))
- Add docs CI files (PR [#227](https://github.com/elastic/elastic-otel-php/pull/227))
- Add release notes files (PR [#218](https://github.com/elastic/elastic-otel-php/pull/218))
- Fixed packages build script and static checks ([#216](https://github.com/elastic/elastic-otel-php/issues/216)) (PR [#220](https://github.com/elastic/elastic-otel-php/pull/220))
- Update EDOT links (PR [#212](https://github.com/elastic/elastic-otel-php/pull/212))

## 1.0.0 [edot-php-1.0.0-release-notes]

### Features and enhancements [edot-php-1.0.0-features-enhancements]

- OTLP protobuf built-in native serialization (PR [#198](https://github.com/elastic/elastic-otel-php/pull/198)).
- Moved EDOT PHP documentation to elastic/opentelemetry (PR [#197](https://github.com/elastic/elastic-otel-php/pull/197)).

## 0.4.0 [edot-php-0.4.0-release-notes]

### Features and enhancements [edot-php-0.4.0-features-enhancements]

- Inferred spans ([#124](https://github.com/elastic/elastic-otel-php/issues/124)) (PR [#152](https://github.com/elastic/elastic-otel-php/pull/152)).
- Improved OTel to Elatic log writer ([#151](https://github.com/elastic/elastic-otel-php/issues/151)) (PR [#154](https://github.com/elastic/elastic-otel-php/pull/154)).
- Dependency composer autoloader guard to force use of EDOT delivered code ([#64](https://github.com/elastic/elastic-otel-php/issues/64)) (PR [#165](https://github.com/elastic/elastic-otel-php/pull/165)).

### Fixes [edot-php-0.4.0-fixes]

- Removed HTTP related attributes from transaction span for CLI script (PR [#153](https://github.com/elastic/elastic-otel-php/pull/153)).
- Included class name into inferred span name (PR [#190](https://github.com/elastic/elastic-otel-php/pull/190)).
- Fixed "tools/build/build_native.sh" implementation of "--conan_user_home"  (PR [#180](https://github.com/elastic/elastic-otel-php/pull/180))
- Fixed calling of original compile function ([#64](https://github.com/elastic/elastic-otel-php/issues/64)) (PR [#170](https://github.com/elastic/elastic-otel-php/pull/170)).
- Fixed error handler when executor is not started  (PR [#159](https://github.com/elastic/elastic-otel-php/pull/159)).
- Fixed supported PHP versions in post-install script  (PR [#163](https://github.com/elastic/elastic-otel-php/pull/163)).
- Fixed inferred spans (PR [#168](https://github.com/elastic/elastic-otel-php/pull/168)).
- Passing fully formatted log to syslog (PR [#172](https://github.com/elastic/elastic-otel-php/pull/172)).

### Other [edot-php-0.4.0-other]

- Added infra/basic component tests  (PR [#120](https://github.com/elastic/elastic-otel-php/pull/120)).
- CI for component tests  (PR [#169](https://github.com/elastic/elastic-otel-php/pull/169)).
- Added component test for Inferred Spans feature  (PR [#182](https://github.com/elastic/elastic-otel-php/pull/182)).
- Run OpenTelemetry Instrumentations tests with EDOT ([#146](https://github.com/elastic/elastic-otel-php/issues/146)) ([#160](https://github.com/elastic/elastic-otel-php/issues/160)) (PR [#160](https://github.com/elastic/elastic-otel-php/pull/160)).
- Debug option ELASTIC_OTEL_DEBUG_PHP_HOOKS_ENABLED to log data from all instrumented hooks (PR [#155](https://github.com/elastic/elastic-otel-php/pull/155))
- Implemented PDOAutoInstrumentationTest (PR [#192](https://github.com/elastic/elastic-otel-php/pull/192)).
- Tests of instrumentation of functions/methods in namespace (PR [#167](https://github.com/elastic/elastic-otel-php/pull/167)).
- Added testing for "has-remote-parent" to curl auto-instrumentation component test (PR [#187](https://github.com/elastic/elastic-otel-php/pull/187)).
- Added supported versions of instrumented frameworks  (PR [#166](https://github.com/elastic/elastic-otel-php/pull/166)).
- Added supported technologies docs, updated limitations ([#148](https://github.com/elastic/elastic-otel-php/issues/148)) ([#164](https://github.com/elastic/elastic-otel-php/issues/164)) (PR [#164](https://github.com/elastic/elastic-otel-php/pull/164)).
- Troubleshooting guide and updated configuration guide ([#148](https://github.com/elastic/elastic-otel-php/issues/148)) ([#162](https://github.com/elastic/elastic-otel-php/issues/162)) (PR [#162](https://github.com/elastic/elastic-otel-php/pull/162)).
- Added missing step to the development guide for adding or removing PHP version support (PR [#161](https://github.com/elastic/elastic-otel-php/pull/161)).

## 0.3.0 [edot-php-0.3.0-release-notes]

### Features and enhancements [edot-php-0.3.0-features-enhancements]

- Added support for PHP 8.4 ([#130](https://github.com/elastic/elastic-otel-php/issues/130)) (PR [#132](https://github.com/elastic/elastic-otel-php/pull/132)).
- Drop PHP 8.0 support due to end of life and lack of security updates ([#128](https://github.com/elastic/elastic-otel-php/issues/128)) (PR [#129](https://github.com/elastic/elastic-otel-php/pull/129)).
- Automatic transaction span with configurable grouping ([#125](https://github.com/elastic/elastic-otel-php/issues/125))  (PR [#126](https://github.com/elastic/elastic-otel-php/pull/126)).
- Enabled curl auto instrumentation ([#121](https://github.com/elastic/elastic-otel-php/issues/121)) (PR [#121](https://github.com/elastic/elastic-otel-php/pull/121)).
- Enabled MySQLi instrumentation ([#135](https://github.com/elastic/elastic-otel-php/issues/135)) (PR [#145](https://github.com/elastic/elastic-otel-php/pull/145)).
- Debug mode to instrument all user functions ([#144](https://github.com/elastic/elastic-otel-php/issues/144)) (PR [#144](https://github.com/elastic/elastic-otel-php/pull/144)).
- Selective logging level per feature ([#103](https://github.com/elastic/elastic-otel-php/issues/103)) (PR [#112](https://github.com/elastic/elastic-otel-php/pull/112)).
- Forced OTLP Exporter to send customized EDOT User-Agent header ([#123](https://github.com/elastic/elastic-otel-php/issues/123))  (PR [#133](https://github.com/elastic/elastic-otel-php/pull/133)).

### Fixes [edot-php-0.3.0-fixes]

- Don't throw exception if instrumented function is missing ([#142](https://github.com/elastic/elastic-otel-php/issues/142)) (PR [#142](https://github.com/elastic/elastic-otel-php/pull/142)).

### Other [edot-php-0.3.0-other]

- Added static check and GitHub workflow for it ([#137](https://github.com/elastic/elastic-otel-php/issues/137)) (PR [#137](https://github.com/elastic/elastic-otel-php/pull/137)).
- Autoloader for Elastic classes ([#115](https://github.com/elastic/elastic-otel-php/issues/115)), Use EDOT logging in OTel logging ([#116](https://github.com/elastic/elastic-otel-php/issues/116)) (PR [#117](https://github.com/elastic/elastic-otel-php/pull/117)).
- Implemented automatic github release notes generator and changlog helper script ([#122](https://github.com/elastic/elastic-otel-php/issues/122)) (PR [#127](https://github.com/elastic/elastic-otel-php/pull/127)).
- Moved ignore platform requirements from build script to composer file. ([#143](https://github.com/elastic/elastic-otel-php/issues/143)) (PR [#143](https://github.com/elastic/elastic-otel-php/pull/143)).
- Adapted code to assume .php.template files converted to .php ([#138](https://github.com/elastic/elastic-otel-php/issues/138)) (PR [#138](https://github.com/elastic/elastic-otel-php/pull/138)).
- Switch ARM64 build from qemu to native runner ([#108](https://github.com/elastic/elastic-otel-php/issues/108))  (PR [#111](https://github.com/elastic/elastic-otel-php/pull/111)).
- Update toolset to latest gcc and conan ([#108](https://github.com/elastic/elastic-otel-php/issues/108)) (PR [#110](https://github.com/elastic/elastic-otel-php/pull/110)).

## 0.2.0 [edot-php-0.2.0-release-notes]

### Features and enhancements [edot-php-0.2.0-features-enhancements]

- Asynchronous (background) transport for traces, logs and metrics ([#101](https://github.com/elastic/elastic-otel-php/issues/101)) (PR [#102](https://github.com/elastic/elastic-otel-php/pull/102)).
- Timeout after which the asynchronous (background) transfer will interrupt data transmission during process termination ([#106](https://github.com/elastic/elastic-otel-php/issues/106)) (PR [#107](https://github.com/elastic/elastic-otel-php/pull/107)).
- Improved documentation.

## 0.1.0 [edot-php-0.1.0-release-notes]

Initial alpha release.
