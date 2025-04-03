--TEST--
AutoZval_getNumberAsLong
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getNumberAsLong(1234));
    var_dump(AutoZval::getNumberAsLong(98.9));
    var_dump(AutoZval::getNumberAsLong(98.01));

    try {
        var_dump(AutoZval::getNumberAsLong(true));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getNumberAsLong("str"));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }


});

echo 'Test completed';
?>
--EXPECTF--
Test start
int(1234)
int(98)
int(98)
getNumberAsLong exception: Not a number
getNumberAsLong exception: Not a number
Test completed
