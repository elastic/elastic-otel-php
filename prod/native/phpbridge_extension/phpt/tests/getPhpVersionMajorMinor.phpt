--TEST--
getPhpVersionMajorMinor
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

class TestClass {
  public $testProperty = "hello";
}

$versions = getPhpVersionMajorMinor();

if ($versions[0] == PHP_MAJOR_VERSION && $versions[1] == PHP_MINOR_VERSION) {
  echo "ALL OK\n";
}

echo 'Test completed';
?>
--EXPECTF--
ALL OK
Test completed
