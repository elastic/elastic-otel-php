--TEST--
instrumentation - internal method
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=INFO
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);


elastic_otel_hook("DateTime", "format", function () {
  echo "*** prehook()\n";
  echo "args:\n";
	var_dump(func_get_args());
  return ["Y/m/d"];
}, function () : string {
  echo "*** posthook()\n";
  echo "args:\n";
	var_dump(func_get_args());
  return "Maybe 2040?";
});

$dt = new DateTime('2010-01-01');

var_dump($dt->format("Y-m-d"));

echo "Test completed\n";
?>
--EXPECTF--
*** prehook()
args:
array(6) {
  [0]=>
  object(DateTime)#%d (3) {
    ["date"]=>
    string(26) "2010-01-01 00:00:00.000000"
    ["timezone_type"]=>
    int(3)
    ["timezone"]=>
    string(3) "UTC"
  }
  [1]=>
  array(1) {
    [0]=>
    string(5) "Y-m-d"
  }
  [2]=>
  string(8) "DateTime"
  [3]=>
  string(6) "format"
  [4]=>
  NULL
  [5]=>
  NULL
}
*** posthook()
args:
array(8) {
  [0]=>
  object(DateTime)#%d (3) {
    ["date"]=>
    string(26) "2010-01-01 00:00:00.000000"
    ["timezone_type"]=>
    int(3)
    ["timezone"]=>
    string(3) "UTC"
  }
  [1]=>
  array(1) {
    [0]=>
    string(5) "Y/m/d"
  }
  [2]=>
  string(10) "2010/01/01"
  [3]=>
  NULL
  [4]=>
  string(8) "DateTime"
  [5]=>
  string(6) "format"
  [6]=>
  NULL
  [7]=>
  NULL
}
string(11) "Maybe 2040?"
Test completed