--TEST--
AutoZval_setString
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    $rv = AutoZval::setString('this is a string');
    var_dump($rv);
});

echo 'Test completed';
?>
--EXPECTF--
Test start
string(16) "this is a string"
Test completed
