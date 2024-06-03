--TEST--
isObjectOfClass - unknown class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
}

$obj = new TestClass;
var_dump(isObjectOfClass("UnknownClass", $obj));

echo 'Test completed';
?>
--EXPECTF--
bool(false)
Test completed
