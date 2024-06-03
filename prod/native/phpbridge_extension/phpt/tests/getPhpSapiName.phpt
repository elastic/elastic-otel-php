--TEST--
getPhpSapiName
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

var_dump(getPhpSapiName());

echo 'Test completed';
?>
--EXPECTF--
string(3) "cli"
Test completed
