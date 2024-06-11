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

echo "enabled\n";
var_dump(getenv('ELASTIC_OTEL_ENABLED'));
var_dump(elastic_otel_is_enabled());
var_dump(elastic_otel_get_config_option_by_name('enabled'));

echo "secret_token\n";
var_dump(getenv('ELASTIC_OTEL_SECRET_TOKEN'));
var_dump(elastic_otel_get_config_option_by_name('secret_token'));

echo "server_url\n";
var_dump(getenv('ELASTIC_OTEL_SERVER_URL'));
var_dump(elastic_otel_get_config_option_by_name('server_url'));

echo "service_name\n";
var_dump(getenv('ELASTIC_OTEL_SERVICE_NAME'));
var_dump(elastic_otel_get_config_option_by_name('service_name'));

echo 'Test completed'
?>
--EXPECT--
enabled
string(23) "not_valid_boolean_value"
bool(false)
bool(false)
secret_token
string(6) "\|<>|/"
string(6) "\|<>|/"
server_url
string(6) "<\/\/>"
string(6) "<\/\/>"
service_name
string(6) "/\><\/"
string(6) "/\><\/"
Test completed
