--TEST--
getCallArguments - reflaction of user class
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
    public function test() {
        var_dump(getCallArguments(0)); // getCallArguments - internal -  array(1) - int(0)
        var_dump(getCallArguments(1)); // TestClass - array(1) - string
        var_dump(getCallArguments(2)); // ReflectionMethod - internal - array(object, string)
        var_dump(getCallArguments(3)); // main scope - array(0)
    }
}

$obj = new ReflectionClass('TestClass');

$obj->getMethod('test')->invoke(new TestClass, "test function arg");


echo 'Test completed';
?>
--EXPECTF--
array(1) {
  [0]=>
  int(0)
}
array(1) {
  [0]=>
  string(17) "test function arg"
}
array(2) {
  [0]=>
  object(TestClass)#3 (0) {
  }
  [1]=>
  string(17) "test function arg"
}
array(0) {
}
Test completed
