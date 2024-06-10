--TEST--
When value in ini is invalid the fallback is the default and not environment variable
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
ELASTIC_OTEL_ASSERT_LEVEL=O_n
ELASTIC_OTEL_MEMORY_TRACKING_LEVEL=ALL
ELASTIC_OTEL_VERIFY_SERVER_CERT=false
--INI--
elastic_otel.memory_tracking_level=not a valid memory tracking level
elastic_otel.verify_server_cert=not a valid bool
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

// assert_level is not set in ini so it falls back on env vars
elasticApmAssertSame("elastic_otel_get_config_option_by_name('assert_level')", elastic_otel_get_config_option_by_name('assert_level'), ELASTIC_OTEL_ASSERT_LEVEL_O_N);
elasticApmAssertSame("getenv('ELASTIC_OTEL_ASSERT_LEVEL')", getenv('ELASTIC_OTEL_ASSERT_LEVEL'), 'O_n');

// memory_tracking_level is set in ini but the value is invalid so it falls back on default (which is `ELASTIC_OTEL_MEMORY_TRACKING_LEVEL_NOT_SET) and not the value set by env vars (which is ELASTIC_OTEL_MEMORY_TRACKING_LEVEL_ALL)
elasticApmAssertSame("elastic_otel_get_config_option_by_name('memory_tracking_level')", elastic_otel_get_config_option_by_name('memory_tracking_level'), ELASTIC_OTEL_MEMORY_TRACKING_LEVEL_NOT_SET);
elasticApmAssertSame("getenv('ELASTIC_OTEL_MEMORY_TRACKING_LEVEL')", getenv('ELASTIC_OTEL_MEMORY_TRACKING_LEVEL'), 'ALL');

// verify_server_cert is set in ini but the value is invalid so it falls back on default (which is `true`) and not the value set by env vars (which is `false`)
elasticApmAssertSame("elastic_otel_get_config_option_by_name('verify_server_cert')", elastic_otel_get_config_option_by_name('verify_server_cert'), true);
elasticApmAssertSame("ini_get('elastic_otel.verify_server_cert')", ini_get('elastic_otel.verify_server_cert'), 'not a valid bool');

echo 'Test completed'
?>
--EXPECT--
Test completed
