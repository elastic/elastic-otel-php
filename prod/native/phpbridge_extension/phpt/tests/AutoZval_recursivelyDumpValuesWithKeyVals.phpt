--TEST--
AutoZval_recursivelyDumpValuesWithKeyVals - prints php values (arrays with keys) to stdout (resursively)
--INI--
extension=/elastic/phpbridge.so
--FILE--
<?php
declare(strict_types=1);

$resource = fopen(__FILE__, 'r');
$ref = &$resource;

$array = [
    'one',
    'two',
    1,
    2,
    1.1,
    2.2,
    "three",
    "fourth",
    'second array values' => ['second', 'array'],
    $resource,
    new stdClass(),
    null,
    true,
    false,
    $ref,
];

// Corner cases https://www.php.net/manual/en/language.types.array.php
unset($array[2]); // remove element at index 2
unset($array[5]); // remove index 5 - creates a gap
$array[42] = 'forty-two'; // non-sequential numeric key
$array[-1] = 'negative key'; // negative key
$array[''] = 'empty string key'; // empty string as key
$array["0"] = 'string zero'; // string "0" vs int 0
$array[0] = 'int zero'; // overwrite key 0
$array[true] = 'bool true'; // key "1"
$array[false] = 'bool false'; // key "0", overwrites int 0 again will overwrite 'one'
$array[null] = 'null key'; // key "" again - same as empty string key - will override empty string
$array['1.1'] = 'string float'; // string numeric key
$array[1.1] = 'float key'; // converted to int(1) - will overwrite 'two' and also 'bool true'
$array[PHP_INT_MAX] = 'max int'; // huge index

$before = memory_get_usage();
AutoZval::iterateKeyValueArray($array);
$after = memory_get_usage();

if ($before != $after) {
    echo "Memory diff: " . ($after - $before) . " bytes\n";
}

echo 'Test completed';
?>
--EXPECTF--
Deprecated: %s
array(18): {
key: 0 => 'bool false'
key: 1 => 'float key'
key: 3 => long: 2
key: 4 => double: 1.1
key: 6 => 'three'
key: 7 => 'fourth'
key: 'second array values' => array(2): {
key: 0 => 'second'
key: 1 => 'array'
}
key: 8 => isResource: 1
key: 9 => isObject: 1
key: 10 => isNull: 1
key: 11 => bool: 1
key: 12 => bool: 0
key: 13 => isResource: 1
key: 42 => 'forty-two'
key: -1 => 'negative key'
key: '' => 'null key'
key: '1.1' => 'string float'
key: 9223372036854775807 => 'max int'
}
Test completed
