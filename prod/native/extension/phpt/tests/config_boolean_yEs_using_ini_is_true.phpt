--TEST--
Boolean configuration option value 'yEs' (in this case using ini file) should be interpreted as true and it should be case insensitive
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file=/elastic/php/bootstrap_php_part.php
elastic_apm.enabled=yEs
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), true);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), true);

echo 'Test completed'
?>
--EXPECT--
Test completed
