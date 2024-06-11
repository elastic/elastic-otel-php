--TEST--
Configuration in ini file has higher precedence than environment variables
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_LOG_FILE=log_file_from_env_vars.txt
ELASTIC_OTEL_LOG_LEVEL_FILE=off
--INI--
elastic_otel.log_file=log_file_from_ini.txt
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_OTEL_LOG_FILE')", getenv('ELASTIC_OTEL_LOG_FILE'), 'log_file_from_env_vars.txt');

elasticApmAssertSame("ini_get('elastic_otel.log_file')", ini_get('elastic_otel.log_file'), 'log_file_from_ini.txt');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('log_file')", elastic_otel_get_config_option_by_name('log_file'), 'log_file_from_ini.txt');

echo 'Test completed'
?>
--EXPECT--
Test completed
