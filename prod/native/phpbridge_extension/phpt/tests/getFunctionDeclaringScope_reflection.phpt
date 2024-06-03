--TEST--
getFunctionDeclaringScope - reflaction of user class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getFunctionDeclaringScope(0)); // getFunctionDeclaringScope
        var_dump(getFunctionDeclaringScope(1)); // TestClass
        var_dump(getFunctionDeclaringScope(2)); // ReflectionMethod
        var_dump(getFunctionDeclaringScope(3)); // main scope
    }
}

$obj = new ReflectionClass('TestClass');

$obj->getMethod('test')->invoke(new TestClass);


echo 'Test completed';
?>
--EXPECTF--
NULL
string(9) "TestClass"
string(16) "ReflectionMethod"
NULL
Test completed
