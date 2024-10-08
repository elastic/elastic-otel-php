#!/bin/bash

function show_help {

echo -e "$0 -b build_architecture -p php_version [-t tests]

Arguments description:
    -b build_architecture   required, architecture of agent so library, f.ex. linux-x86-64
    -p php_version          PHP version f.ex. 80 or 83
    -t tests                Tests to run, folder or particular test file name. Default: tests
    -f path                 Generate test failures archive and save it in path
    -h                      print this help
"
}

TESTS_TO_RUN=tests

while getopts "b:p:t:f:h" opt; do
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
    f)  TESTS_FAILURES_ARCHIVE="${OPTARG}"
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

DOCKER_PLATFORM="linux/x86_64"
if [[ "${BUILD_ARCHITECTURE}" =~ arm64$ ]]; then
     DOCKER_PLATFORM="linux/arm64"
fi
echo "Running on platform ${DOCKER_PLATFORM}";

ELASTIC_AGENT_PHP_PATH=${PWD}/../../../php

LOCAL_TMP_DIR=$(mktemp -d)

LOCAL_LOG_FAILED_TESTS=${LOCAL_TMP_DIR}/test-run-failures.log
touch ${LOCAL_LOG_FAILED_TESTS}

LOCAL_LOG_TEST_RUN=${LOCAL_TMP_DIR}/test-run.log
touch ${LOCAL_LOG_TEST_RUN}

LOG_FAILED_TESTS=/phpt-tests/test-run-failures.log
LOG_TEST_RUN=/phpt-tests/test-run.log

RUN_TESTS=/usr/local/lib/php/build/run-tests.php

# copy test folder or test file into temp folder
mkdir -p "${LOCAL_TMP_DIR}/$(dirname ${TESTS_TO_RUN})"
cp  -R "${TESTS_TO_RUN}" "${LOCAL_TMP_DIR}/${TESTS_TO_RUN}"

docker run --rm \
    --platform ${DOCKER_PLATFORM} \
    -v ${LOCAL_TMP_DIR}/tests:/phpt-tests/tests \
    -v ${LOCAL_LOG_FAILED_TESTS}:${LOG_FAILED_TESTS} \
    -v ${LOCAL_LOG_TEST_RUN}:${LOG_TEST_RUN} \
    -v ${ELASTIC_AGENT_PHP_PATH}:/elastic/php \
    -v ${ELASTIC_AGENT_SO_PATH}:/elastic/elastic_otel_php.so \
    -w /phpt-tests \
    php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli${ALPINE_IMAGE} sh -c "php -n ${RUN_TESTS} -s ${LOG_TEST_RUN} -w ${LOG_FAILED_TESTS} ${TESTS_TO_RUN}"

if [ $? -ne 0 ]; then
    echo "Test failed"

    pushd ${LOCAL_TMP_DIR}
    tar -czf ${TESTS_FAILURES_ARCHIVE}  .
    popd
    rm -rf ${LOCAL_TMP_DIR}

    exit 1
fi

rm -rf ${LOCAL_TMP_DIR}
