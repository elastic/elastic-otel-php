--TEST--
getFunctionName
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

var_dump(getFunctionName(0));

echo 'Test completed';
?>
--EXPECTF--
string(15) "getFunctionName"
Test completed
