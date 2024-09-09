--TEST--
instrumentation - user method - retry to instrument after class loads
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

echo "Hooking class doesn't exist:".PHP_EOL;

var_dump(elastic_otel_hook("testclass", "userspace", function () {
	echo "*** prehook userspace()\n";
 }, function () : string {
	echo "*** posthook userspace()\n";
}));

require("includes/test_class.inc");

echo "Hooking class loaded:".PHP_EOL;

var_dump(elastic_otel_hook("testclass", "userspace", function () {
	echo "*** prehook userspace()\n";
 }, function () : string {
	echo "*** posthook userspace()\n";
}));

echo "Test completed\n";
?>
--EXPECTF--
Hooking class doesn't exist:
bool(false)
Hooking class loaded:
bool(true)
Test completed
