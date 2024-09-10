--TEST--
instrumentation - internal func pre hook only
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_otel_hook(NULL, "str_contains", function () {
	echo "*** prehook()\n";
}, NULL);

var_dump(str_contains("elastic obs", "obs"));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook()
bool(true)
Test completed