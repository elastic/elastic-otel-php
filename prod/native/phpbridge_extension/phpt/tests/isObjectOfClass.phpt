--TEST--
isObjectOfClass
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
}

$obj = new TestClass;
var_dump(isObjectOfClass("TestClass", $obj));

echo 'Test completed';
?>
--EXPECTF--
bool(true)
Test completed
