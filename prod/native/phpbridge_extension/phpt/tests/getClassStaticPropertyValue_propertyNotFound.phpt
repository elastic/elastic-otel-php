--TEST--
getClassStaticPropertyValue - property not found
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public static $testProperty = "hello";
}

var_dump(getClassStaticPropertyValue("testclass", "missing"));

echo 'Test completed';
?>
--EXPECTF--
%agetClassStaticPropertyValue property not found
NULL
Test completed
