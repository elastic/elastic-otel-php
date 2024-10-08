--TEST--
Setting configuration options to non-default value (in this case using environment variables)
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ENABLED=0
ELASTIC_OTEL_LOG_FILE=non-default_log_file_value.txt
ELASTIC_OTEL_LOG_LEVEL=CRITICAL
ELASTIC_OTEL_LOG_LEVEL_FILE=TRACE
ELASTIC_OTEL_LOG_LEVEL_SYSLOG=TRACE
ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG=CRITICAL
ELASTIC_OTEL_SECRET_TOKEN=non-default_secret_token_123
ELASTIC_OTEL_SERVER_URL=https://non-default_server_url:4321/some/path
ELASTIC_OTEL_SERVICE_NAME=Non-default Service Name
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), '0');

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), false);

elasticApmAssertSame("elastic_otel_get_config_option_by_name('enabled')", elastic_otel_get_config_option_by_name('enabled'), false);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_FILE')", getenv('ELASTIC_OTEL_LOG_FILE'), 'non-default_log_file_value.txt');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_file')", elastic_otel_get_config_option_by_name('log_file'), 'non-default_log_file_value.txt');

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL')", getenv('ELASTIC_OTEL_LOG_LEVEL'), 'CRITICAL');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level')", elastic_otel_get_config_option_by_name('log_level'), ELASTIC_OTEL_LOG_LEVEL_CRITICAL);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_FILE')", getenv('ELASTIC_OTEL_LOG_LEVEL_FILE'), 'TRACE');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_file')", elastic_otel_get_config_option_by_name('log_level_file'), ELASTIC_OTEL_LOG_LEVEL_TRACE);

//////////////////////////////////////////////
///////////////  log_level_syslog

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_OTEL_LOG_LEVEL_SYSLOG'), 'TRACE');

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_syslog')", elastic_otel_get_config_option_by_name('log_level_syslog'), ELASTIC_OTEL_LOG_LEVEL_TRACE);
}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG'), 'CRITICAL');

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_level_win_sys_debug')", elastic_otel_get_config_option_by_name('log_level_win_sys_debug'), ELASTIC_OTEL_LOG_LEVEL_CRITICAL);
}

echo 'Test completed'
?>
--EXPECT--
Test completed
