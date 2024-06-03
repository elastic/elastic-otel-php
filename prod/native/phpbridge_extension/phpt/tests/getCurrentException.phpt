--TEST--
getCurrentException
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class MyException extends Exception { }

var_dump(getCurrentException(new Exception('Hello exception')));
var_dump(getCurrentException(new MyException('Hello exception')));
var_dump(getCurrentException(new DateTime));


echo 'Test completed';
?>
--EXPECTF--
object(Exception)#1 (7) {
  ["message":protected]=>
  string(15) "Hello exception"
  ["string":"Exception":private]=>
  string(0) ""
  ["code":protected]=>
  int(0)
  ["file":protected]=>
  string(%d) "%agetCurrentException.php"
  ["line":protected]=>
  int(6)
  ["trace":"Exception":private]=>
  array(0) {
  }
  ["previous":"Exception":private]=>
  NULL
}
object(MyException)#1 (7) {
  ["message":protected]=>
  string(15) "Hello exception"
  ["string":"Exception":private]=>
  string(0) ""
  ["code":protected]=>
  int(0)
  ["file":protected]=>
  string(%d) "%agetCurrentException.php"
  ["line":protected]=>
  int(7)
  ["trace":"Exception":private]=>
  array(0) {
  }
  ["previous":"Exception":private]=>
  NULL
}
NULL
Test completed
