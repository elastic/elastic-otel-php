---
navigation_title: Limitations
description: Limitations of the Elastic Distribution of OpenTelemetry PHP.
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

# Limitations

This section describes potential limitations of {{edot}} PHP (EDOT PHP) and how you can work around them.

## OpenTelemetry extension and SDK loaded in parallel with EDOT PHP

Currently, the {{edot}} PHP (EDOT PHP) does not support scenarios where both EDOT and an OpenTelemetry PHP setup are installed in the application. This includes the `opentelemetry.so` extension and the OpenTelemetry PHP SDK.

In such cases, a conflict will occur, preventing both solutions from functioning correctly. To resolve this, remove OpenTelemetry components from your application's `composer.json` and update the project accordingly.

## `open_basedir` PHP configuration option

If the `open_basedir` option ([documentation](https://www.php.net/manual/en/ini.core.php#ini.open-basedir)) is configured in your php.ini, the installation directory of EDOT PHP (by default `/opt/elastic/apm-agent-php`) must be located within one of the paths specified in the `open_basedir` option. Otherwise, EDOT PHP will not be loaded correctly.


## `Xdebug` stability and memory issues

We strongly advise against running the agent alongside the Xdebug extension. Using both extensions simultaneously can lead to stability issues in the instrumented application, such as increased memory usage or memory leaks. It is highly recommended to deactivate Xdebug, preferably by deactivating it directly in your `php.ini` configuration.

## File-based configuration (`OTEL_CONFIG_FILE`)

{applies_to}`edot_php: ga 1.7.0`

When using file-based (declarative) configuration:

- Remote configuration (OpAMP) is not available — file-based and remote configuration are mutually exclusive.
- Resource detectors registered through `Registry::registerResourceDetector()` (for example, cloud provider detectors from `opentelemetry-php-contrib`) are not automatically active. They must provide a `ComponentProvider` and be explicitly listed in the YAML `resource.detection/development.detectors` section.
- EDOT PHP ships a built-in `distro` detector for the `telemetry.distro.name` and `telemetry.distro.version` attributes. See [Configuration](../configuration.md#edot-php-resource-detector) for usage.
- Environment variable substitution (`${VAR_NAME}`) in YAML files relies on `$_SERVER` to read values. In web server contexts (Apache, nginx+FPM), process environment variables are not automatically available in `$_SERVER`. To use `${VAR_NAME}` substitution in your YAML configuration, ensure the variables are exposed to PHP:
  - **Apache (mod_php)**: Use `PassEnv VAR_NAME` or `SetEnv VAR_NAME value` in your virtual host configuration.
  - **PHP-FPM**: Set `env[VAR_NAME] = value` in your FPM pool configuration, or set `clear_env = no` to pass all process environment variables.
  - Alternatively, hardcode values directly in the YAML file instead of using `${VAR_NAME}` substitution.
