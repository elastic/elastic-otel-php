--TEST--
AutoZval_recursivelyDumpValues - prints php values to stdout (resursively)
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);


$resource = fopen(__FILE__, 'r');
$someVal = "some referenced value";
$ref = $someVal;

$array = ['one', 'two', 1, 2, 1.1, 2.2, "three", "fourh", ['second', 'array'], $resource, new stdClass(), null, true, false, $ref];
unset($array[2]); // remove element at index 2


$before = memory_get_usage();
AutoZval::iterateArray($array);
$after = memory_get_usage();

if ($before != $after) {
    echo "Memory diff: " . ($after - $before) . " bytes\n";
}

echo 'Test completed';
?>
--EXPECTF--
array(14): {
'one'
'two'
long: 2
double: 1.1
double: 2.2
'three'
'fourh'
array(2): {
'second'
'array'
}
isResource: 1
isObject: 1
isNull: 1
bool: 1
bool: 0
'some referenced value'
}
Test completed
