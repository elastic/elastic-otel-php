<?php

const CRED = "\033[31m";
const CGREEN = "\033[32m";
const CDEF = "\033[39m";

echo CGREEN."Starting package uninstalled smoke test\n".CDEF;

$scopeName = isset($argv[1]) ? $argv[1] . "\\" : "";

echo "Checking if extension is loaded: ";
if (array_search("opentelemetry_distro", get_loaded_extensions()) !== false) {
    echo CRED."FAILED. OpenTelemetry PHP Distro extension found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for internal function 'OpenTelemetry\\Distro\\is_enabled': ";
if (function_exists('OpenTelemetry\\Distro\\is_enabled')) {
    echo CRED."FAILED. OpenTelemetry\\Distro\\is_enabled function found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for {$scopeName}OpenTelemetry\\Distro\\PhpPartFacade class: ";
if (array_search("{$scopeName}OpenTelemetry\\Distro\\PhpPartFacade", get_declared_classes()) !== false) {
    echo CRED."FAILED. {$scopeName}OpenTelemetry\\Distro\\PhpPartFacade class found after uninstall\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo CGREEN."Smoke test passed\n".CDEF;
