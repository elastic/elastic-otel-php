--TEST--
Check that elastic_apm.enabled cannot be set with ini_set()
--ENV--
ELASTIC_APM_LOG_LEVEL_STDERR=CRITICAL
--INI--
extension=/elastic/elastic_otel_php.so
elastic_apm.bootstrap_php_part_file=/elastic/php/bootstrap_php_part.php
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/includes/tests_util.inc';

if (ini_set('elastic_apm.enabled', 'new value') === false) {
    echo 'ini_set returned false';
}
?>
--EXPECT--
ini_set returned false
