--TEST--
getScopeNameOrThis - get object
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getScopeNameOrThis(0)); // getScopeNameOrThis - internal -  NULL
        var_dump(getScopeNameOrThis(1)); // TestClass - object
        var_dump(getScopeNameOrThis(2)); // main scope - NULL
    }
}

$obj = new TestClass;
$obj->test();

echo 'Test completed';
?>
--EXPECTF--
NULL
object(TestClass)#1 (0) {
}
NULL
Test completed
