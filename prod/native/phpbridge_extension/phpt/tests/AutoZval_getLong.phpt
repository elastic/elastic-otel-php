--TEST--
AutoZval_getLong
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getLong(1234));

    try {
        var_dump(AutoZval::getLong(98.9));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getLong(true));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getLong("str"));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }


});

echo 'Test completed';
?>
--EXPECTF--
Test start
int(1234)
getLong exception: Not a long
getLong exception: Not a long
getLong exception: Not a long
Test completed
