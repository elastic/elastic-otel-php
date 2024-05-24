--TEST--
Boolean configuration option value 'FaLSe' (in this case using ini file) should be interpreted as false and it should be case insensitive
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
elastic_apm.enabled=FaLSe
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file=/elastic/php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.php';

elasticApmAssertEqual("ini_get('elastic_apm.enabled')", ini_get('elastic_apm.enabled'), false);

elasticApmAssertSame("elastic_apm_is_enabled()", elastic_apm_is_enabled(), false);

echo 'Test completed'
?>
--EXPECT--
Test completed
