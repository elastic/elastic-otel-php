--TEST--
getClassStaticPropertyValue - class not found
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

var_dump(getClassStaticPropertyValue("testclass", "testProperty"));

echo 'Test completed';
?>
--EXPECTF--
%agetClassStaticPropertyValue class not found
NULL
Test completed
