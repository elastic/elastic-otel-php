--TEST--
instrumentation - user func
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);



function userspace() : void {
  echo "* userspace()\n";
}

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 }, function () : string {
	echo "*** posthook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
  return "this is possible";
});

var_dump(userspace());

echo "Test completed\n";
?>
--EXPECTF--
*** prehook userspace()
* userspace()
*** posthook userspace()
args:
array(8) {
  [0]=>
  NULL
  [1]=>
  array(0) {
  }
  [2]=>
  NULL
  [3]=>
  NULL
  [4]=>
  NULL
  [5]=>
  string(9) "userspace"
  [6]=>
  string(%d) "%a/instrumentation_user_func_change_void_retval.php"
  [7]=>
  int(6)
}
string(16) "this is possible"
Test completed