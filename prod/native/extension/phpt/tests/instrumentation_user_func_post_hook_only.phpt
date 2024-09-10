--TEST--
instrumentation - user func - post hook only
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=info
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);



function userspace($arg1, $arg2, $arg3) {
	echo "* userspace() called.\n";
}

elastic_otel_hook(NULL, "userspace", NULL, function () {
	echo "*** posthook userspace()\n";
});

userspace("first", 2, 3);

echo "Test completed\n";
?>
--EXPECTF--
* userspace() called.
*** posthook userspace()
Test completed