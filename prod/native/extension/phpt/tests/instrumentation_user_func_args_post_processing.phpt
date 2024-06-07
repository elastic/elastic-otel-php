--TEST--
instrumentation - user func - args post processing
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
	return [0=>"replaced_1st", "arg2" => "replaced_2nd_byName", 10=>"added10th", 7=>"replaced_7th", "last_unindexed_argument"];
 }, function () : string {
	echo "*** posthook userspace()\n";
 	echo "args:\n";
	var_dump(func_get_args());
	return "modified_rv";
});


var_dump(userspace("first", 2, 3, "fourth", ["fifth in array"], "sixth", 7));

echo 'Test completed';
?>
--EXPECTF--
*** prehook userspace()
args:
array(6) {
  [0]=>
  NULL
  [1]=>
  array(7) {
    [0]=>
    string(5) "first"
    [1]=>
    int(2)
    [2]=>
    int(3)
    [3]=>
    string(6) "fourth"
    [4]=>
    array(1) {
      [0]=>
      string(14) "fifth in array"
    }
    [5]=>
    string(5) "sixth"
    [6]=>
    int(7)
  }
  [2]=>
  NULL
  [3]=>
  string(9) "userspace"
  [4]=>
  string(%d) "%a/instrumentation_user_func_args_post_processing.php"
  [5]=>
  int(6)
}
* userspace() body start.
args:
array(12) {
  [0]=>
  string(12) "replaced_1st"
  [1]=>
  string(19) "replaced_2nd_byName"
  [2]=>
  int(3)
  [3]=>
  string(6) "fourth"
  [4]=>
  array(1) {
    [0]=>
    string(14) "fifth in array"
  }
  [5]=>
  string(5) "sixth"
  [6]=>
  int(7)
  [7]=>
  string(12) "replaced_7th"
  [8]=>
  NULL
  [9]=>
  NULL
  [10]=>
  string(9) "added10th"
  [11]=>
  string(23) "last_unindexed_argument"
}
* userspace() body end
*** posthook userspace()
args:
array(8) {
  [0]=>
  NULL
  [1]=>
  array(12) {
    [0]=>
    string(12) "replaced_1st"
    [1]=>
    string(19) "replaced_2nd_byName"
    [2]=>
    int(3)
    [3]=>
    string(6) "fourth"
    [4]=>
    array(1) {
      [0]=>
      string(14) "fifth in array"
    }
    [5]=>
    string(5) "sixth"
    [6]=>
    int(7)
    [7]=>
    string(12) "replaced_7th"
    [8]=>
    NULL
    [9]=>
    NULL
    [10]=>
    string(9) "added10th"
    [11]=>
    string(23) "last_unindexed_argument"
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
  string(%d) "%a/instrumentation_user_func_args_post_processing.php"
  [7]=>
  int(6)
}
string(11) "modified_rv"
Test completed