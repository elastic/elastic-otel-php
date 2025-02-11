--TEST--
getNewlyCompiledFiles
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

$indexes = getNewlyCompiledFiles(0, 0);


echo "require\n";
require("includes/someClass.inc");

$newIndexes = getNewlyCompiledFiles($indexes[0], $indexes[1]);

if ($newIndexes[1] == $indexes[1]) {
  echo "FAILURE, function index not changed\n";
}

echo 'Test completed';
?>
--EXPECTF--
[%a] %a/tests/getNewlyCompiledFiles.php
require
[%a] %a/tests/includes/someClass.inc
Test completed
