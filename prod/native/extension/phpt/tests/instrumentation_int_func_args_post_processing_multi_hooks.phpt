--TEST--
instrumentation - user func - args post processing in multiple hooks
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

elastic_apm_hook(NULL, "str_contains", function () {
	echo "*** prehook 1()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return [0=>"this is new text to search in"];
 }, function () : string {
  echo "*** posthook 1()\n";
  return "modified_rv_in_1st";
});

elastic_apm_hook(NULL, "str_contains", function () {
	echo "*** prehook 2()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return ["needle" => "search"];
 }, function () {
	echo "*** posthook 2()\n";
});

elastic_apm_hook(NULL, "str_contains", function () {
	echo "*** prehook 3()\n";
  echo "args:\n";
	var_dump(func_get_args());
 }, function () : string {
	echo "*** posthook 3()\n";
  echo "args:\n";
	var_dump(func_get_args());
  return "this is insane - we're returning bool isntead of bool";
});

var_dump(str_contains("elastic obs", "obs"));

echo 'Test completed';
?>
--EXPECTF--
*** prehook 1()
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
*** prehook 2()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(29) "this is new text to search in"
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
*** prehook 3()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(29) "this is new text to search in"
    [1]=>
    string(6) "search"
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
*** posthook 1()
*** posthook 2()
*** posthook 3()
args:
array(8) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(29) "this is new text to search in"
    [1]=>
    string(6) "search"
  }
  [2]=>
  string(18) "modified_rv_in_1st"
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
string(53) "this is insane - we're returning bool isntead of bool"
Test completed