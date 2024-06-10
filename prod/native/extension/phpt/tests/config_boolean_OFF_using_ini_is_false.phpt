--TEST--
Boolean configuration option value 'OFF' (in this case using ini file) should be interpreted as false and it should be case insensitive
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
elastic_otel.enabled=OFF
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertEqual("ini_get('elastic_otel.enabled')", ini_get('elastic_otel.enabled'), false);

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
