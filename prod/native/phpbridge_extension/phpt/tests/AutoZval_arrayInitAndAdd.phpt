--TEST--
AutoZval_arrayInitAndAdd
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

    AutoZval::arrayAddNextWithRef($ar, "some value");
    AutoZval::arrayAddNextWithRef($ar, "some value 2");
    AutoZval::arrayAddNextWithRef($ar, 12);
    AutoZval::arrayAddNextWithRef($ar, true);

    debug_zval_dump($ar);
});

echo 'Test completed';
?>
--EXPECTF--
Test start
array(0) refcount(2){
}
array(4)%arefcount(2){
  [0]=>
  string(10) "some value" interned
  [1]=>
  string(12) "some value 2" interned
  [2]=>
  int(12)
  [3]=>
  bool(true)
}
Test completed
