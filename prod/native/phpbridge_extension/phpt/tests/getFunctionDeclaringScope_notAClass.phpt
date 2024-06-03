--TEST--
getFunctionDeclaringScope
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

var_dump(getFunctionDeclaringScope(0));

echo 'Test completed';
?>
--EXPECTF--
NULL
Test completed
