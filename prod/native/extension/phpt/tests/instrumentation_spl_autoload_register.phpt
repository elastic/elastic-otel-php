--TEST--
instrumentation - spl_autoload_register
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_otel_hook(NULL, "spl_autoload_register", function () {
	echo "*** prehook()\n";
 }, function () {
	echo "*** posthook()\n";
});





spl_autoload_register(function () { }, true, false);

echo "Test completed\n";
?>
--EXPECTF--
*** prehook()
*** posthook()
Test completed