--TEST--
getExceptionName
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class MyException extends Exception { }

var_dump(getExceptionName(new Exception('Hello exception')));
var_dump(getExceptionName(new MyException('Hello exception')));


echo 'Test completed';
?>
--EXPECTF--
string(9) "Exception"
string(11) "MyException"
Test completed
