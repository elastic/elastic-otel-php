--TEST--
compileAndExecuteFile - file not found
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

compileAndExecuteFile("fileNotFound");

echo 'Test completed';
?>
--EXPECTF--
%aNative exception caught: 'Unable to open file for compilation 'fileNotFound''
Test completed
