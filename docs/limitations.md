<!--
Goal of this doc:
The user is aware of EDOT PHP limitations
-->

# Limitations

This section describes potential limitations of Elastic Distribution of OpenTelemetry PHP (EDOT PHP)
and how you can work around them.

## `open_basedir` PHP configuration option

Please be aware that if the `open_basedir`option
([documentaion](https://www.php.net/manual/en/ini.core.php#ini.open-basedir))
is configured in your php.ini,
the installation directory of EDOT PHP (by default `/opt/elastic/apm-agent-php`)
must be located within a path included in the
`open_basedir` option value.
Otherwise, EDOT PHP will not be loaded correctly.
