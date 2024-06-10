--TEST--
instrumentation - user func - return value replacement in multiple hooks - last takes effect
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

function userspace($arg1, $arg2, $arg3) : string {
	echo "* userspace() body.\n";
	return "userspace_rv";
}

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 }, function () : mixed {
	echo "*** posthook userspace()\n";
	return "first_rv";
});

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** second prehook userspace()\n";
 }, function () : mixed {
	echo "*** second posthook userspace()\n";
	return "second_rv";
});

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** third prehook userspace()\n";
 }, function () : mixed {
	echo "*** third posthook userspace()\n";
	return "third_rv";
});

var_dump(userspace("first", 2, 3));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook userspace()
*** second prehook userspace()
*** third prehook userspace()
* userspace() body.
*** posthook userspace()
*** second posthook userspace()
*** third posthook userspace()
string(8) "third_rv"
Test completed