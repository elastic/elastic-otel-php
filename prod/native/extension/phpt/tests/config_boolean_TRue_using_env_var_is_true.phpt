--TEST--
Boolean configuration option value 'TRue' (in this case using environment variable) should be interpreted as true and it should be case insensitive
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=TRue
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), 'TRue');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
