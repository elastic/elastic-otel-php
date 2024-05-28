--TEST--
callMethod
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  function test() {
    echo "hello".PHP_EOL;
    var_dump(func_get_args());
    return "return value from hello";
  }
}

$obj = new TestClass;
var_dump(callMethod($obj, "test", [123, 456]));

echo 'Test completed';
?>
--EXPECTF--
hello
array(2) {
  [0]=>
  int(123)
  [1]=>
  int(456)
}
%acallMethod rv: 1
string(23) "return value from hello"
Test completed
