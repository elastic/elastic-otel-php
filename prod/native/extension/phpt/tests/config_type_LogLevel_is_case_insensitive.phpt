--TEST--
Configuration values of type LogLevel are case insensitive
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_LOG_LEVEL=warning
ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG=TRaCe
--INI--
elastic_otel.log_level_syslog=INFO
elastic_otel.log_level_file=dEbUg
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level')", elastic_otel_get_config_option_by_name('log_level'), ELASTIC_OTEL_LOG_LEVEL_WARNING);

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_syslog')", elastic_otel_get_config_option_by_name('log_level_syslog'), ELASTIC_OTEL_LOG_LEVEL_INFO);
}

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_win_sys_debug')", elastic_otel_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_OTEL_LOG_LEVEL_TRACE);
}

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_file')", elastic_otel_get_config_option_by_name('log_level_file'), ELASTIC_OTEL_LOG_LEVEL_DEBUG);

echo 'Test completed'
?>
--EXPECT--
Test completed
