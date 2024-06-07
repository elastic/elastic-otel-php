--TEST--
Boolean configuration option value 1 (in this case using environment variable) should be interpreted as true
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=1
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), '1');

elasticApmAssertSame('elastic_apm_is_enabled()', elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
