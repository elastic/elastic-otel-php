--TEST--
getClassPropertyValue
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

$obj = new TestClass;

var_dump(getClassPropertyValue("testclass", "testProperty", $obj));

echo 'Test completed';
?>
--EXPECTF--
string(5) "hello"
Test completed
