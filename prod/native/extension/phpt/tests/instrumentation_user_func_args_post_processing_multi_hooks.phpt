--TEST--
instrumentation - internal func - args post processing in multiple hooks
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);


function userspace($arg1, $arg2) {
	echo "* userspace() body start.\n";
 	echo "args:\n";
	var_dump(func_get_args());
	echo "* userspace() body end\n";
	return "userspace_rv";

}

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return [0=>"replaced_1st_in_first_hook"];
 }, function () : string {
  echo "*** posthook userspace()\n";
  return "modified_rv_in_1st";
});

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return ["arg2" => "replaced_2nd_byName_in_second_hook"];
 }, function () {
	echo "*** posthook userspace()\n";
});

elastic_otel_hook(NULL, "userspace", function () {
	echo "*** prehook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return [10=>"added10th_in_third_hook"];
 }, function () {
	echo "*** posthook userspace()\n";
});

var_dump(userspace("first", 2));

echo 'Test completed';
?>
--EXPECTF--
*** prehook userspace()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(5) "first"
    [1]=>
    int(2)
  }
  [2]=>
  NULL
  [3]=>
  string(9) "userspace"
  [4]=>
  string(%d) "%a/instrumentation_user_func_args_post_processing_multi_hooks.php"
  [5]=>
  int(5)
}
*** prehook userspace()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(26) "replaced_1st_in_first_hook"
    [1]=>
    int(2)
  }
  [2]=>
  NULL
  [3]=>
  string(9) "userspace"
  [4]=>
  string(%d) "%a/instrumentation_user_func_args_post_processing_multi_hooks.php"
  [5]=>
  int(5)
}
*** prehook userspace()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(2) {
    [0]=>
    string(26) "replaced_1st_in_first_hook"
    [1]=>
    string(34) "replaced_2nd_byName_in_second_hook"
  }
  [2]=>
  NULL
  [3]=>
  string(9) "userspace"
  [4]=>
  string(%d) "%a/instrumentation_user_func_args_post_processing_multi_hooks.php"
  [5]=>
  int(5)
}
* userspace() body start.
args:
array(11) {
  [0]=>
  string(26) "replaced_1st_in_first_hook"
  [1]=>
  string(34) "replaced_2nd_byName_in_second_hook"
  [2]=>
  NULL
  [3]=>
  NULL
  [4]=>
  NULL
  [5]=>
  NULL
  [6]=>
  NULL
  [7]=>
  NULL
  [8]=>
  NULL
  [9]=>
  NULL
  [10]=>
  string(23) "added10th_in_third_hook"
}
* userspace() body end
*** posthook userspace()
*** posthook userspace()
*** posthook userspace()
string(18) "modified_rv_in_1st"
Test completed