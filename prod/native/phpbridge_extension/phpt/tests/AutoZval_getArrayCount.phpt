--TEST--
AutoZval_getArrayCount
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getArrayCount([]));
    var_dump(AutoZval::getArrayCount([1,2,3,4]));
    var_dump(AutoZval::getArrayCount([1=>2,3,4=>4]));
});

echo 'Test completed';
?>
--EXPECTF--
Test start
int(0)
int(4)
int(3)
Test completed
