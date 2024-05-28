--TEST--
getClassPropertyValue - static property
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public static $testProperty = "hello";
}

$obj = new TestClass;

var_dump(getClassPropertyValue("testclass", "testProperty", $obj));

echo 'Test completed';
?>
--EXPECTF--
NULL
Test completed
