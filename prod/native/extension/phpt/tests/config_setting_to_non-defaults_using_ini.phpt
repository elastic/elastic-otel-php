--TEST--
Setting configuration options to non-default value (in this case using ini file)
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_otel.enabled=0
elastic_otel.log_file=non-default_log_file_value.txt
elastic_otel.log_level=CRITICAL
elastic_otel.log_level_file=TRACE
elastic_otel.log_level_syslog=TRACE
elastic_otel.log_level_win_sys_debug=CRITICAL
elastic_otel.secret_token=non-default_secret_token_123
elastic_otel.server_url=https://non-default_server_url:4321/some/path
elastic_otel.service_name=Non-default Service Name
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elastic_otel.enabled')", ini_get('elastic_otel.enabled'), false);

elasticApmAssertSame("elastic_otel_get_config_option_by_name('enabled')", elastic_otel_get_config_option_by_name('enabled'), false);

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), false);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("ini_get('elastic_otel.log_file')", ini_get('elastic_otel.log_file'), 'non-default_log_file_value.txt');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_file')", elastic_otel_get_config_option_by_name('log_file'), 'non-default_log_file_value.txt');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("ini_get('elastic_otel.log_level')", ini_get('elastic_otel.log_level'), 'CRITICAL');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level')", elastic_otel_get_config_option_by_name('log_level'), ELASTIC_OTEL_LOG_LEVEL_CRITICAL);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("ini_get('elastic_otel.log_level_file')", ini_get('elastic_otel.log_level_file'), 'TRACE');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_file')", elastic_otel_get_config_option_by_name('log_level_file'), ELASTIC_OTEL_LOG_LEVEL_TRACE);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_otel.log_level_syslog')", ini_get('elastic_otel.log_level_syslog'), 'TRACE');

    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_syslog')", elastic_otel_get_config_option_by_name('log_level_syslog'), ELASTIC_OTEL_LOG_LEVEL_TRACE);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("ini_get('elastic_otel.log_level_win_sys_debug')", ini_get('elastic_otel.log_level_win_sys_debug'), 'CRITICAL');
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_win_sys_debug')", elastic_otel_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_OTEL_LOG_LEVEL_CRITICAL);
}

echo 'Test completed'
?>
--EXPECT--
Test completed
