--TEST--
When value in ini is invalid, extension parses it the same way as PHP - returns false and not returning environment variable
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_VERIFY_SERVER_CERT=true
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
elastic_otel.verify_server_cert=not a valid bool
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

// according to ini parser in PHP everything except "yes, true, on" values are returned as return atoi(string) !=0 - so it always returns false

var_dump(elastic_otel_get_config_option_by_name('verify_server_cert'));
var_dump(ini_get('elastic_otel.verify_server_cert'));

echo 'Test completed'
?>
--EXPECT--
bool(false)
string(16) "not a valid bool"
Test completed
