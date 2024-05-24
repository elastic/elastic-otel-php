#!/bin/bash

function show_help {

echo -e "$0 -b build_architecture -p php_version [-t tests]

Arguments description:
    -b build_architecture   required, architecture of agent so library, f.ex. linux-x86-64
    -p php_version          PHP version f.ex. 80 or 83 
    -t tests                Tests to run, folder or particular test file name. Default: tests
    -h                      print this help
"
}

TESTS_TO_RUN=tests

while getopts "b:p:t:h" opt; do
    case "$opt" in
    h|\?)
        show_help
        exit 0
        ;;
    b)  BUILD_ARCHITECTURE="${OPTARG}"
        ;;
    p)  PHP_VERSION="${OPTARG}"
        ;;
    t)  TESTS_TO_RUN="${OPTARG}"
        ;;        
    esac
done

if [ -z "${BUILD_ARCHITECTURE}" ] || [ -z "${PHP_VERSION}" ]; then
    show_help
    exit 1
fi

ELASTIC_AGENT_SO_PATH=${PWD}/../../_build/${BUILD_ARCHITECTURE}-release/extension/code/elastic_otel_php_${PHP_VERSION}.so
if [ ! -f ${ELASTIC_AGENT_SO_PATH} ]; then
    echo "Native build not found: '${ELASTIC_AGENT_SO_PATH}'"
    exit 1
fi

if [[ ! "${BUILD_ARCHITECTURE}" =~ "musl" ]]; then
    echo "Running using glibc docker image";
else
    echo "Running using musl docker image";
    ALPINE_IMAGE=-alpine
fi

ELASTIC_AGENT_PHP_PATH=${PWD}/../../../php

LOG_TEST_RUN=/phpt-tests/test-run.log


LOCAL_LOG_FAILED_TESTS=$(mktemp)
LOG_FAILED_TESTS=/phpt-tests/test-run-failures.log

RUN_TESTS=/usr/local/lib/php/build/run-tests.php

docker run --rm \
    -v ./tests:/phpt-tests/tests \
    -v ./tests_util:/phpt-tests/tests_util \
    -v ${LOCAL_LOG_FAILED_TESTS}:${LOG_FAILED_TESTS} \
    -v ${ELASTIC_AGENT_PHP_PATH}:/elastic/php \
    -v ${ELASTIC_AGENT_SO_PATH}:/elastic/elastic_otel_php.so \
    -w /phpt-tests \
    php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli${ALPINE_IMAGE} sh -c "php -n ${RUN_TESTS} -w ${LOG_FAILED_TESTS} ${TESTS_TO_RUN} 2>&1 | tee ${LOG_TEST_RUN}"

if [ -s ${LOCAL_LOG_FAILED_TESTS} ]; then
    echo "Test failed"
    rm ${LOCAL_LOG_FAILED_TESTS}
    exit 1
fi

rm ${LOCAL_LOG_FAILED_TESTS}
