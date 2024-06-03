--TEST--
compileAndExecuteFile
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

compileAndExecuteFile("compileAndExecuteFile.inc");

echo 'Test completed';
?>
--EXPECTF--
This is the result of executing the compiled script
Test completed
