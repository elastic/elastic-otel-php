--TEST--
getFunctionName - user function
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getFunctionName(1));
    }
}

$obj = new TestClass;
$obj->test();

echo 'Test completed';
?>
--EXPECTF--
string(4) "test"
Test completed
