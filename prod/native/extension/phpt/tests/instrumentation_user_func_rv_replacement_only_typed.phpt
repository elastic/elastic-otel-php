--TEST--
instrumentation - user func - return value replacement only in explicitly specified return type hooks
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

function userspace($arg1, $arg2, $arg3) {
	echo "* userspace() body.\n";
	return "userspace_rv";
}

elastic_apm_hook(NULL, "userspace", NULL, function () : int {
	echo "*** posthook userspace()\n";
	return 12;
});

elastic_apm_hook(NULL, "userspace", NULL, function () : mixed {
	echo "*** second posthook userspace()\n";
	return "second_rv";
});

elastic_apm_hook(NULL, "userspace", NULL, function () {
	echo "*** third posthook userspace()\n";
	return "third_rv";
});

var_dump(userspace("first", 2, 3));

echo "Test completed\n";
?>
--EXPECTF--
* userspace() body.
*** posthook userspace()
*** second posthook userspace()
*** third posthook userspace()
string(9) "second_rv"
Test completed