--TEST--
getFunctionDeclarationLineNo - reflaction of user class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getFunctionDeclarationLineNo(0)); // getFunctionDeclarationLineNo - internal - NULL
        var_dump(getFunctionDeclarationLineNo(1)); // TestClass
        var_dump(getFunctionDeclarationLineNo(2)); // ReflectionMethod - internal - NULL
        var_dump(getFunctionDeclarationLineNo(3)); // main scope
    }
}

$obj = new ReflectionClass('TestClass');

$obj->getMethod('test')->invoke(new TestClass);


echo 'Test completed';
?>
--EXPECTF--
NULL
int(5)
NULL
int(1)
Test completed
