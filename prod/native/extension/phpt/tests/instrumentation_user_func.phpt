--TEST--
instrumentation - user func
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);



function userspace($arg1, $arg2, $arg3) {
	echo "* userspace() body start.\n";
 	echo "args:\n";
	var_dump(func_get_args());
	echo "* userspace() body end\n";
	return "userspace_rv";
}

elastic_apm_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
 }, function () {
	echo "*** posthook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
});

var_dump(userspace("first", 2, 3));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook userspace()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(3) {
    [0]=>
    string(5) "first"
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
  [2]=>
  NULL
  [3]=>
  string(9) "userspace"
  [4]=>
  string(%d) "%a/instrumentation_user_func.php"
  [5]=>
  int(6)
}
* userspace() body start.
args:
array(3) {
  [0]=>
  string(5) "first"
  [1]=>
  int(2)
  [2]=>
  int(3)
}
* userspace() body end
*** posthook userspace()
args:
array(8) {
  [0]=>
  NULL
  [1]=>
  array(3) {
    [0]=>
    string(5) "first"
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
  [2]=>
  string(12) "userspace_rv"
  [3]=>
  NULL
  [4]=>
  NULL
  [5]=>
  string(9) "userspace"
  [6]=>
  string(%d) "%a/instrumentation_user_func.php"
  [7]=>
  int(6)
}
string(12) "userspace_rv"
Test completed