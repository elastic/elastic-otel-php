--TEST--
getPhpInfo
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

getPhpInfo();

echo 'Test completed';
?>
--EXPECTF--
%aPHP-INFO: phpinfo()
PHP Version%a
%aelastic_phpbridge%a
Test completed
