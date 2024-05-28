--TEST--
getClassPropertyValue - object of different class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

var_dump(getClassPropertyValue("testclass", "testProperty", 1234));

echo 'Test completed';
?>
--EXPECTF--
%agetClassPropertyValue property not found
NULL
Test completed
