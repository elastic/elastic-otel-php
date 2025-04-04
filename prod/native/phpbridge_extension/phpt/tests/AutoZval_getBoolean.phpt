--TEST--
AutoZval_getBoolean
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getBoolean(true));
    var_dump(AutoZval::getBoolean(false));

    try {
        var_dump(AutoZval::getBoolean(0));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getBoolean("str"));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }


});

echo 'Test completed';
?>
--EXPECTF--
Test start
bool(true)
bool(false)
getBoolean exception: Not a boolean
getBoolean exception: Not a boolean
Test completed
