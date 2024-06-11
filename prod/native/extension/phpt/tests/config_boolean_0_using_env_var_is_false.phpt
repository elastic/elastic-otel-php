--TEST--
Boolean configuration option value 0 (in this case using environment variable) should be interpreted as false
--ENV--
ELASTIC_OTEL_ENABLED=0
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file=/elastic/php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_OTEL_ENABLED')", getenv('ELASTIC_OTEL_ENABLED'), '0');

elasticApmAssertSame('elastic_otel_is_enabled()', elastic_otel_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
