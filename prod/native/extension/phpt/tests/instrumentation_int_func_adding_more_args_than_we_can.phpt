--TEST--
instrumentation - internal func - adding more arguements than we can
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
 	echo "args:\n";
	var_dump(func_get_args());
  return [0 => "new haystack", 1 => "new needle", 2 => "we can't do that!"];
 }, function () {
	echo "*** posthook()\n";
 	echo "args:\n";
	var_dump(func_get_args());
});




var_dump(str_contains("elastic obs", "obs"));

echo "Test completed\n";
?>
--EXPECTF--
%astr_contains() expects exactly 2 arguments, 3 given%a