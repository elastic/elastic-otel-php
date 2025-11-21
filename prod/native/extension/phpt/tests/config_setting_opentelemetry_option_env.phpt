--TEST--
Setting OpenTelemetry configuration option using environment variable
--ENV--
OTEL_EXPORTER_OTLP_CERTIFICATE=/path/to/cert.pem
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);


var_dump(elastic_otel_get_config_option_by_name('OTEL_EXPORTER_OTLP_CERTIFICATE'));

echo 'Test completed'
?>
--EXPECT--
string(17) "/path/to/cert.pem"
Test completed
