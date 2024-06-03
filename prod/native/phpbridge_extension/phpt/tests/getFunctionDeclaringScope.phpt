--TEST--
getFunctionDeclaringScope - user class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getFunctionDeclaringScope(1));
    }
}

$obj = new TestClass;
$obj->test();

echo 'Test completed';
?>
--EXPECTF--
string(9) "TestClass"
Test completed
