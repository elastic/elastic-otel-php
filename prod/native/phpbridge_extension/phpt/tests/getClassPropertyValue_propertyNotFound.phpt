--TEST--
getClassPropertyValue - property not found
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

$obj = new DateTime;

var_dump(getClassPropertyValue("testclass", "missingProperty", $obj));

echo 'Test completed';
?>
--EXPECTF--
NULL
Test completed
