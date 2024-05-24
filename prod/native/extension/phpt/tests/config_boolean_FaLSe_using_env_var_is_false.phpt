--TEST--
Boolean configuration option value 'FaLSe' (in this case using environment variable) should be interpreted as false and it should be case insensitive
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_APM_ENABLED=FaLSe
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file=/elastic/php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertSame("getenv('ELASTIC_APM_ENABLED')", getenv('ELASTIC_APM_ENABLED'), 'FaLSe');

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
