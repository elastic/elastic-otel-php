--TEST--
AutoZval_arrayInitAndAddAssoc
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

require('includes/leakCheck.inc');

echo "Test start\n";

leakCheck(function() {
    $ar = AutoZval::arrayInit();
    debug_zval_dump($ar);

    AutoZval::arrayAddAssocWithRef($ar, "", "some value");
    AutoZval::arrayAddAssocWithRef($ar, "non-empty", "some value 2");

    debug_zval_dump($ar);
});

echo 'Test completed';
?>
--EXPECTF--
Test start
array(0) refcount(2){
}
array(2)%arefcount(2){
  [""]=>
  string(10) "some value" interned
  ["non-empty"]=>
  string(12) "some value 2" interned
}
Test completed
