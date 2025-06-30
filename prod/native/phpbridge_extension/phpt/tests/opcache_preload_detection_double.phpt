--TEST--
Detection of opcache preload feature
--DESCRIPTION--
Detection of double detection of opcache preload - in case we will not be able to distinguish preload from normal request
--XFAIL--
Expected to fail, preload should be detected only once
--INI--
extension=/elastic/phpbridge.so
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/opcache_preload_detection.inc
opcache.preload_user=root
--SKIPIF--
<?php
if (PHP_VERSION_ID < 70400) die("skip ElasticApmSkipTest Unsupported PHP version");
?>
--FILE--
<?php
declare(strict_types=1);

echo 'Result in request script'.PHP_EOL;
flush();
detectOpcachePreload();

echo 'Preloaded function exists: '.function_exists('preloadedFunction').PHP_EOL;
echo 'Test completed';
?>
--EXPECTF--
Result in preload script
%a detectOpcachePreload: 1
Result in request script
%a detectOpcachePreload: 1
Preloaded function exists: 1
Test completed
