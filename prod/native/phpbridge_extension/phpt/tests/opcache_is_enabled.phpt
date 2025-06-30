--TEST--
Detection of opcache enabled state
--INI--
extension=/elastic/phpbridge.so
opcache.enable_cli=1
opcache.optimization_level=-1
--FILE--
<?php
declare(strict_types=1);

isOpcacheEnabled();
echo 'Test completed';
?>
--EXPECTF--
%aisOpcacheEnabled: 1
Test completed
