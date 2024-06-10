--TEST--
Boolean configuration option value 'yes' (in this case using environment variable) should be interpreted as true and it should be case insensitive
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ENABLED=yEs
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), 'yEs');

elasticApmAssertSame("elastic_otel_is_enabled()", elastic_otel_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
