#!/usr/bin/env bash
set -e -o pipefail
#set -x

function main() {
    source "./tools/shared.sh"

    if [ -z "${composer_run_component_tests_log_file}" ]; then
        composer_run_component_tests_log_file=/otel_php_distro_tests/logs/composer_-_run_component_tests.log
    fi

    source tools/test/component/unpack_matrix_row.sh
    unpack_matrix_row "${OTEL_PHP_TESTS_MATRIX_ROW:?}" "OTEL_PHP_TESTS" "true"

    env | grep OTEL_PHP_TESTS_ | sort

    composer_command=(composer run-script -- run_component_tests)

    if [ -n "${OTEL_PHP_TESTS_GROUP+x}" ]; then
        composer_command=("${composer_command[@]}" --group "${OTEL_PHP_TESTS_GROUP}")
    fi

    if [ -n "${OTEL_PHP_TESTS_FILTER+x}" ]; then
        composer_command=("${composer_command[@]}" --filter "${OTEL_PHP_TESTS_FILTER}")
    fi

    env | sort

    "${composer_command[@]}" 2>&1 | tee "${composer_run_component_tests_log_file}"
}

main "$@"
