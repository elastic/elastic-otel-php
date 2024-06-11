--TEST--
Boolean configuration option value 0 (in this case using ini file) should be interpreted as false
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_otel.enabled=0
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("ini_get('elastic_otel.enabled')", ini_get('elastic_otel.enabled'), '0');

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
