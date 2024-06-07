--TEST--
instrumentation - internal func - variadic arguments
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_apm_hook(NULL, "min", function () {
	echo "*** prehook()\n";
 	echo "args:\n";
	var_dump(func_get_args());
  return [-10, -20, -30, -40, -50, -60, -100];
 }, function () {
	echo "*** posthook()\n";
 	echo "args:\n";
	var_dump(func_get_args());
});




var_dump(min(10, 20, 30, 40));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(4) {
    [0]=>
    int(10)
    [1]=>
    int(20)
    [2]=>
    int(30)
    [3]=>
    int(40)
  }
  [2]=>
  NULL
  [3]=>
  string(3) "min"
  [4]=>
  NULL
  [5]=>
  NULL
}
*** posthook()
args:
array(8) {
  [0]=>
  NULL
  [1]=>
  array(7) {
    [0]=>
    int(-10)
    [1]=>
    int(-20)
    [2]=>
    int(-30)
    [3]=>
    int(-40)
    [4]=>
    int(-50)
    [5]=>
    int(-60)
    [6]=>
    int(-100)
  }
  [2]=>
  int(-100)
  [3]=>
  NULL
  [4]=>
  NULL
  [5]=>
  string(3) "min"
  [6]=>
  NULL
  [7]=>
  NULL
}
int(-100)
Test completed