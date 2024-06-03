--TEST--
isObjectOfClass - different class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
}

$obj = new TestClass;
var_dump(isObjectOfClass("DateTime", $obj));

$obj = new DateTime;
var_dump(isObjectOfClass("TestClass", $obj));


echo 'Test completed';
?>
--EXPECTF--
bool(false)
bool(false)
Test completed
