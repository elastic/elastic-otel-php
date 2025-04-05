--TEST--
AutoZval_getNumberAsDouble
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getNumberAsDouble(1234));
    var_dump(AutoZval::getNumberAsDouble(98.9));
    var_dump(AutoZval::getNumberAsDouble(98.01));

    try {
        var_dump(AutoZval::getNumberAsDouble(true));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getNumberAsDouble("str"));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }


});

echo 'Test completed';
?>
--EXPECTF--
Test start
float(1234)
float(98.9)
float(98.01)
getNumberAsDouble exception: Not a number
getNumberAsDouble exception: Not a number
Test completed
