--TEST--
AutoZval_getDouble
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getDouble(98.9));

    try {
        var_dump(AutoZval::getDouble(1234));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getDouble(true));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getDouble("str"));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }


});

echo 'Test completed';
?>
--EXPECTF--
Test start
float(98.9)
getDouble exception: Not a double
getDouble exception: Not a double
getDouble exception: Not a double
Test completed
