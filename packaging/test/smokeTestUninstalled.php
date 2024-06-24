<?php

const CRED = "\033[31m";
const CGREEN = "\033[32m";
const CDEF = "\033[39m";

echo CGREEN."Starting package uninstalled smoke test\n".CDEF;

echo "Checking if extension is loaded: ";
if (array_search("elastic_otel", get_loaded_extensions()) !== false) {
    echo CRED."FAILED. Elastic OpenTelemetry extension found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for internal function 'elastic_otel_is_enabled': ";
if (array_search("elastic_otel_is_enabled", get_defined_functions()["internal"]) !== false) {
    echo CRED."FAILED. Elastic OpenTelemetry extension function 'elastic_otel_is_enabled' found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for PhpPartFacade class: ";
if (array_search("Elastic\OTel\AutoInstrument\PhpPartFacade", get_declared_classes()) !== false) {
    echo CRED."FAILED. Elastic\OTel\AutoInstrument\PhpPartFacade class not found. Bootstrap failed\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo CGREEN."Smoke test passed\n".CDEF;
