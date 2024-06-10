--TEST--
Boolean configuration option value 'FaLSe' (in this case using environment variable) should be interpreted as false and it should be case insensitive
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ENABLED=FaLSe
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), 'FaLSe');

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
