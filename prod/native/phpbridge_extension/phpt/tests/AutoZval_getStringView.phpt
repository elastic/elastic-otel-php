--TEST--
AutoZval_getStringView
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    var_dump(AutoZval::getStringView("some string"));
    var_dump(AutoZval::getStringView('some other string'));

    try {
        var_dump(AutoZval::getStringView(1234));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }

    try {
        var_dump(AutoZval::getStringView(true));
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
    }
});

echo 'Test completed';
?>
--EXPECTF--
Test start
string(11) "some string"
string(17) "some other string"
getStringView exception: Not a string
getStringView exception: Not a string
Test completed
