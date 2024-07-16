<?php

const CRED = "\033[31m";
const CGREEN = "\033[32m";
const CDEF = "\033[39m";

echo CGREEN."Starting package smoke test\n".CDEF;

echo "Checking if extension is loaded: ";
if (array_search("elastic_otel", get_loaded_extensions()) === false) {
    echo CRED."FAILED. Elastic OpenTelemetry extension not found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for internal function 'elastic_otel_is_enabled': ";
if (array_search("elastic_otel_is_enabled", get_extension_funcs("elastic_otel")) === false) {
    echo CRED."FAILED. Elastic OpenTelemetry extension function 'elastic_otel_is_enabled' not found\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;


echo "Checking if extension is enabled: ";
if (elastic_otel_is_enabled() !== true) {
    echo CRED."FAILED. Elastic OpenTelemetry extension is not enabled\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Looking for PhpPartFacade class: ";
if (array_search("Elastic\OTel\PhpPartFacade", get_declared_classes()) === false) {
    echo CRED."FAILED. Elastic\OTel\PhpPartFacade class not found. Bootstrap failed\n".CDEF;
    exit(1);
}
echo CGREEN."OK\n".CDEF;

echo "Trying to log something to stderr: ";
Elastic\OTel\BootstrapStageLogger::logCritical("This is just a message to test logger", __LINE__, __FUNCTION__);
echo CGREEN."OK\n".CDEF;

echo CGREEN."Smoke test passed\n".CDEF;
