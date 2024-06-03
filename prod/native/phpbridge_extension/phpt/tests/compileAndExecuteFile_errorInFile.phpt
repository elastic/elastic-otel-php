--TEST--
compileAndExecuteFile - error in file
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

compileAndExecuteFile("compileAndExecuteFile_errorInFile.inc");

echo 'Test completed';
?>
--EXPECTF--
%aNative exception caught: 'Error during execution of file 'compileAndExecuteFile_errorInFile.inc'. Error thrown with message 'Undefined constant "compilerrrrror"' in /phpt-tests/tests/compileAndExecuteFile_errorInFile.inc:3'
Test completed
