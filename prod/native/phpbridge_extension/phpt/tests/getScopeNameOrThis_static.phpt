--TEST--
getScopeNameOrThis - get static
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public static function test() {
        var_dump(getScopeNameOrThis(0)); // getScopeNameOrThis - internal -  NULL
        var_dump(getScopeNameOrThis(1)); // TestClass
        var_dump(getScopeNameOrThis(2)); // main scope - NULL
    }
}

TestClass::test();

echo 'Test completed';
?>
--EXPECTF--
NULL
string(9) "TestClass"
NULL
Test completed
