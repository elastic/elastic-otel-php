--TEST--
Setting configuration option to invalid value via ini file
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_otel.enabled=not valid boolean value
elastic_otel.secret_token=\|<>|/
elastic_otel.server_url=<\/\/>
elastic_otel.service_name=/\><\/
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

//////////////////////////////////////////////
///////////////  enabled

echo "enabled\n";
var_dump(ini_get('elastic_otel.enabled'));
var_dump(elastic_otel_get_config_option_by_name('enabled'));
var_dump(elastic_otel_is_enabled());

echo 'Test completed'
?>
--EXPECT--
enabled
string(23) "not valid boolean value"
bool(false)
bool(false)
Test completed
