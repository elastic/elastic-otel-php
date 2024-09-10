--TEST--
instrumentation - internal func post hook only
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_otel_hook(NULL, "str_contains", NULL, function () {
	echo "*** posthook()\n";
});

var_dump(str_contains("elastic obs", "obs"));

echo "Test completed\n";
?>
--EXPECTF--
*** posthook()
bool(true)
Test completed