--TEST--
Detection of opcache disabled state
--INI--
extension=/elastic/phpbridge.so
zend_extension=opcache.so
opcache.enable_cli=0
opcache.optimization_level=-1
--FILE--
<?php
declare(strict_types=1);

isOpcacheEnabled();
echo 'Test completed';
?>
--EXPECTF--
%aisOpcacheEnabled: 0
Test completed
