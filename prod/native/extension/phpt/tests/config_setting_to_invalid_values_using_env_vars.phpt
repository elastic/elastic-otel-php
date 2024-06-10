--TEST--
Setting configuration option to invalid value via environment variables
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ENABLED=not_valid_boolean_value
ELASTIC_OTEL_ASSERT_LEVEL=|:/:\:|
ELASTIC_OTEL_SECRET_TOKEN=\|<>|/
ELASTIC_OTEL_SERVER_URL=<\/\/>
ELASTIC_OTEL_SERVICE_NAME=/\><\/
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), 'not_valid_boolean_value');

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), true);

elasticApmAssertSame("elastic_otel_get_config_option_by_name('enabled')", elastic_otel_get_config_option_by_name('enabled'), true);

//////////////////////////////////////////////
///////////////  assert_level

elasticApmAssertSame("getenv('ELASTIC_OTEL_ASSERT_LEVEL')", getenv('ELASTIC_OTEL_ASSERT_LEVEL'), '|:/:\:|');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('assert_level')", elastic_otel_get_config_option_by_name('assert_level'), ELASTIC_OTEL_ASSERT_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("getenv('ELASTIC_OTEL_SECRET_TOKEN')", getenv('ELASTIC_OTEL_SECRET_TOKEN'), '\|<>|/');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('secret_token')", elastic_otel_get_config_option_by_name('secret_token'), '\|<>|/');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("getenv('ELASTIC_OTEL_SERVER_URL')", getenv('ELASTIC_OTEL_SERVER_URL'), '<\/\/>');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('server_url')", elastic_otel_get_config_option_by_name('server_url'), '<\/\/>');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("getenv('ELASTIC_OTEL_SERVICE_NAME')", getenv('ELASTIC_OTEL_SERVICE_NAME'), '/\><\/');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('service_name')", elastic_otel_get_config_option_by_name('service_name'), '/\><\/');

echo 'Test completed'
?>
--EXPECT--
Test completed
