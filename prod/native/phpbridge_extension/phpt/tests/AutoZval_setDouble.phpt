--TEST--
AutoZval_setDouble
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    $rv = AutoZval::setDouble(10.1234);
    var_dump($rv);
});

echo 'Test completed';
?>
--EXPECTF--
Test start
float(10.1234)
Test completed
