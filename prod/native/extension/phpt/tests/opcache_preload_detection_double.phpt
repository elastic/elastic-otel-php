--TEST--
Detection of opcache preload feature
--DESCRIPTION--
Detection of double detection of opcache preload - in case we will not be able to distinguish preload from normal request
--XFAIL--
Expected to fail, preload should be detected only once
--ENV--
ELASTIC_OTEL_LOG_LEVEL_STDERR=DEBUG
ELASTIC_OTEL_ENABLED=true
--INI--
elastic_otel.enabled = 1
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/opcache_preload_detection.inc
opcache.preload_user=root
extension=/elastic/elastic_otel_php.so
elastic_otel.bootstrap_php_part_file={PWD}/includes/bootstrap_mock.inc
--SKIPIF--
<?php
if (PHP_VERSION_ID < 70400) die("skip ElasticApmSkipTest Unsupported PHP version");
?>
--FILE--
<?php
declare(strict_types=1);

echo 'Test completed';
?>
--EXPECTF--
%aopcache.preload request detected on init%aopcache.preload request detected on shutdown%aopcache.preload request detected on init%aopcache.preload request detected on shutdown%a
Test completed%a