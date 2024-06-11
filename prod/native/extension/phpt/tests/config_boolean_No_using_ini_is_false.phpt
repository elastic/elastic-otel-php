--TEST--
Boolean configuration option value 'no' (in this case using ini file) should be interpreted as false and it should be case insensitive
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=ERROR
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
elastic_otel.enabled=No
--FILE--
<?php
declare(strict_types=1);

var_dump(ini_get('elastic_otel.enabled'));
var_dump(elastic_otel_is_enabled());

echo 'Test completed'
?>
--EXPECT--
string(0) ""
bool(false)
Test completed
