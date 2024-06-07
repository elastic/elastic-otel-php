--TEST--
Which configuration options are dynamic
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

$dynamicConfigOptNames = [ 'log_level' ];

elasticApmAssertSame('elastic_apm_get_number_of_dynamic_config_options()', elastic_apm_get_number_of_dynamic_config_options(), count($dynamicConfigOptNames));

echo 'Test completed'
?>
--EXPECT--
Test completed
