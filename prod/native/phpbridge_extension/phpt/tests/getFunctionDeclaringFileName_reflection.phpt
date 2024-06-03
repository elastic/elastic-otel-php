--TEST--
getFunctionDeclarationFileName - reflaction of user class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getFunctionDeclarationFileName(0)); // getFunctionDeclarationFileName - internal - NULL
        var_dump(getFunctionDeclarationFileName(1)); // TestClass
        var_dump(getFunctionDeclarationFileName(2)); // ReflectionMethod - internal - NULL
        var_dump(getFunctionDeclarationFileName(3)); // main scope
    }
}

$obj = new ReflectionClass('TestClass');

$obj->getMethod('test')->invoke(new TestClass);


echo 'Test completed';
?>
--EXPECTF--
NULL
string(%d) "%a/getFunctionDeclaringFileName_reflection.php"
NULL
string(%d) "%a/getFunctionDeclaringFileName_reflection.php"
Test completed
