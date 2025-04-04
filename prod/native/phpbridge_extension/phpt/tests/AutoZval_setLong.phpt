--TEST--
AutoZval_setLong
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    $rv = AutoZval::setLong(101234);
    var_dump($rv);
});

leakCheck(function() {
    $rv = AutoZval::setLong(-101234);
    var_dump($rv);
});


echo 'Test completed';
?>
--EXPECTF--
Test start
int(101234)
int(-101234)
Test completed
