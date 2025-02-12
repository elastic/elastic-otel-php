--TEST--
getCompiledFiles
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

getCompiledFiles();

echo "require\n";
require("includes/someClass.inc");

getCompiledFiles();


echo 'Test completed';
?>
--EXPECTF--
[%a] %a/tests/getCompiledFiles.php
require
[%a] %a/tests/getCompiledFiles.php
[%a] %a/tests/includes/someClass.inc
Test completed
