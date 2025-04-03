--TEST--
AutoZval_setNull
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    $rv = AutoZval::setNull();
    var_dump($rv);
});

echo 'Test completed';
?>
--EXPECTF--
Test start
NULL
Test completed
