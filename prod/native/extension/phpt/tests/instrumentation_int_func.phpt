--TEST--
instrumentation - internal func
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_apm_hook(NULL, "str_contains", function () {
	echo "*** prehook()\n";
 	echo "args:\n";
	var_dump(func_get_args());
 }, function () {
	echo "*** posthook()\n";
 	echo "args:\n";
	var_dump(func_get_args());
});




var_dump(str_contains("elastic obs", "obs"));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(11) "elastic obs"
    [1]=>
    string(3) "obs"
  }
  [2]=>
  NULL
  [3]=>
  string(12) "str_contains"
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
  array(2) {
    [0]=>
    string(11) "elastic obs"
    [1]=>
    string(3) "obs"
  }
  [2]=>
  bool(true)
  [3]=>
  NULL
  [4]=>
  NULL
  [5]=>
  string(12) "str_contains"
  [6]=>
  NULL
  [7]=>
  NULL
}
bool(true)
Test completed