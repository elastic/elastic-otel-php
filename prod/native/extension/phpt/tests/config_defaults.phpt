--TEST--
Verify configuration option's defaults
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ENABLED=
ELASTIC_OTEL_LOG_FILE=
ELASTIC_OTEL_LOG_LEVEL=
ELASTIC_OTEL_LOG_LEVEL_FILE=
ELASTIC_OTEL_LOG_LEVEL_SYSLOG=
ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG=
ELASTIC_OTEL_SECRET_TOKEN=
ELASTIC_OTEL_SERVER_URL=
ELASTIC_OTEL_SERVICE_NAME=
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), false);

elasticApmAssertEqual("ini_get('elastic_otel.enabled')", ini_get('elastic_otel.enabled'), false);

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), true);

elasticApmAssertSame("elastic_otel_get_config_option_by_name('enabled')", elastic_otel_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  log_file

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_FILE')", getenv('ELASTIC_OTEL_LOG_FILE'), false);

elasticApmAssertEqual("ini_get('elastic_otel.log_file')", ini_get('elastic_otel.log_file'), false);

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_file')", elastic_otel_get_config_option_by_name('log_file'), "");

//////////////////////////////////////////////
///////////////  log_level

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL')", getenv('ELASTIC_OTEL_LOG_LEVEL'), false);

elasticApmAssertEqual("ini_get('elastic_otel.log_level')", ini_get('elastic_otel.log_level'), false);

//////////////////////////////////////////////
///////////////  log_level_file

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_FILE')", getenv('ELASTIC_OTEL_LOG_LEVEL_FILE'), false);

elasticApmAssertEqual("ini_get('elastic_otel.log_level_file')", ini_get('elastic_otel.log_level_file'), false);

//////////////////////////////////////////////
///////////////  log_level_syslog

if ( ! elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_SYSLOG')", getenv('ELASTIC_OTEL_LOG_LEVEL_SYSLOG'), false);

    elasticApmAssertEqual("ini_get('elastic_otel.log_level_syslog')", ini_get('elastic_otel.log_level_syslog'), false);

}

//////////////////////////////////////////////
///////////////  log_level_win_sys_debug

if (elasticApmIsOsWindows()) {
    elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG')", getenv('ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG'), false);

    elasticApmAssertEqual("ini_get('elastic_otel.log_level_win_sys_debug')", ini_get('elastic_otel.log_level_win_sys_debug'), false);

}

// //////////////////////////////////////////////
// ///////////////  secret_token

// elasticApmAssertSame("getenv('ELASTIC_OTEL_SECRET_TOKEN')", getenv('ELASTIC_OTEL_SECRET_TOKEN'), false);

// elasticApmAssertEqual("ini_get('elastic_otel.secret_token')", ini_get('elastic_otel.secret_token'), false);

// elasticApmAssertSame("elastic_otel_get_config_option_by_name('secret_token')", elastic_otel_get_config_option_by_name('secret_token'), "");

// //////////////////////////////////////////////
// ///////////////  server_url

// elasticApmAssertSame("getenv('ELASTIC_OTEL_SERVER_URL')", getenv('ELASTIC_OTEL_SERVER_URL'), false);

// elasticApmAssertEqual("ini_get('elastic_otel.server_url')", ini_get('elastic_otel.server_url'), false);

// elasticApmAssertSame("elastic_otel_get_config_option_by_name('server_url')", elastic_otel_get_config_option_by_name('server_url'), 'http://localhost:8200');

// //////////////////////////////////////////////
// ///////////////  service_name

// elasticApmAssertSame("getenv('ELASTIC_OTEL_SERVICE_NAME')", getenv('ELASTIC_OTEL_SERVICE_NAME'), false);

// elasticApmAssertEqual("ini_get('elastic_otel.service_name')", ini_get('elastic_otel.service_name'), false);

// elasticApmAssertSame("elastic_otel_get_config_option_by_name('service_name')", elastic_otel_get_config_option_by_name('service_name'), "");

echo 'Test completed'
?>
--EXPECT--
Test completed
