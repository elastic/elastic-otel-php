--TEST--
instrumentation - interface
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=info
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);

interface ImplementationInterface
{
    public function testfunc();
}

class Implementation implements ImplementationInterface
{
    public function testfunc()
    {
        echo "TestFunc".PHP_EOL;
    }
}


elastic_otel_hook("ImplementationInterface", "testfunc", function () {
	echo "*** prehook() called from impl\n";
 }, function () {
	echo "*** posthook() called from impl\n";
});

$impl = new Implementation;

$impl->testfunc();

echo "Test completed\n";
?>
--EXPECTF--
*** prehook() called from impl
TestFunc
*** posthook() called from impl
Test completed
