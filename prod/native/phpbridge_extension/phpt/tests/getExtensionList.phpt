--TEST--
getExtensionList
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

getExtensionList();

echo 'Test completed';
?>
--EXPECTF--
%aname: 'Core' version:%a
%aname: 'elastic_phpbridge' version:%a
Test completed
