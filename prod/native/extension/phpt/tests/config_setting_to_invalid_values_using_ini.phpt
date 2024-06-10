--TEST--
Setting configuration option to invalid value via ini file
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_otel.enabled=not valid boolean value
elastic_otel.assert_level=|:/:\:|
elastic_otel.secret_token=\|<>|/
elastic_otel.server_url=<\/\/>
elastic_otel.service_name=/\><\/
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

//////////////////////////////////////////////
///////////////  enabled

elasticApmAssertEqual("ini_get('elastic_otel.enabled')", ini_get('elastic_otel.enabled'), 'not valid boolean value');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('enabled')", elastic_otel_get_config_option_by_name('enabled'), true);

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), true);

//////////////////////////////////////////////
///////////////  assert_level

elasticApmAssertSame("ini_get('elastic_otel.assert_level')", ini_get('elastic_otel.assert_level'), '|:/:\:|');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('assert_level')", elastic_otel_get_config_option_by_name('assert_level'), ELASTIC_OTEL_ASSERT_LEVEL_NOT_SET);

//////////////////////////////////////////////
///////////////  secret_token

elasticApmAssertSame("ini_get('elastic_otel.secret_token')", ini_get('elastic_otel.secret_token'), '\|<>|/');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('secret_token')", elastic_otel_get_config_option_by_name('secret_token'), '\|<>|/');

//////////////////////////////////////////////
///////////////  server_url

elasticApmAssertSame("ini_get('elastic_otel.server_url')", ini_get('elastic_otel.server_url'), '<\/\/>');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('server_url')", elastic_otel_get_config_option_by_name('server_url'), '<\/\/>');

//////////////////////////////////////////////
///////////////  service_name

elasticApmAssertSame("ini_get('elastic_otel.service_name')", ini_get('elastic_otel.service_name'), '/\><\/');

elasticApmAssertSame("elastic_otel_get_config_option_by_name('service_name')", elastic_otel_get_config_option_by_name('service_name'), '/\><\/');

echo 'Test completed'
?>
--EXPECT--
Test completed
