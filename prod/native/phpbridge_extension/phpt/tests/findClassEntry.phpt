--TEST--
findClassEntry
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

findClassEntry("datetime");

echo 'Test completed';
?>
--EXPECTF--
%afindClassEntry found: 1
Test completed
