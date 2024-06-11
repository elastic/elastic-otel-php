--TEST--
Check that elastic_otel.enabled cannot be set with ini_set()
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

if (ini_set('elastic_otel.enabled', 'new value') === false) {
    echo 'ini_set returned false';
}
?>
--EXPECT--
ini_set returned false
