--TEST--
Configuration values of type LogLevel: it is enough to provide unambiguous prefix
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_LOG_LEVEL=warn
ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG=TRa
--INI--
elastic_otel.log_level_syslog=Er
elastic_otel.log_level_file=dEb
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

if ( ! extension_loaded( 'elastic_apm' ) ) die( 'Extension elastic_apm must be installed' );

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level')", elastic_otel_get_config_option_by_name('log_level'), ELASTIC_OTEL_LOG_LEVEL_WARNING);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_syslog')", elastic_otel_get_config_option_by_name('log_level_syslog'), ELASTIC_OTEL_LOG_LEVEL_ERROR);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_win_sys_debug')", elastic_otel_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_OTEL_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_file')", elastic_otel_get_config_option_by_name('log_level_file'), ELASTIC_OTEL_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
