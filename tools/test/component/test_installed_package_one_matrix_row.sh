#!/usr/bin/env bash
set -e -o pipefail
set -x

function print_info_about_environment () {
    echo "Current directory: ${PWD}"

    echo 'PHP version:'
    php -v

    echo 'Installed PHP extensions:'
    php -m

    echo 'Set environment variables:'
    env | sort
}

function main() {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    print_info_about_environment

    source "${this_script_dir}/unpack_matrix_row.sh" "${ELASTIC_OTEL_PHP_TESTS_MATRIX_ROW:?}"
    env | grep ELASTIC_OTEL_PHP_TESTS_ | sort

    composer_command=(composer run-script -- run_component_tests)

    if [ -n "${ELASTIC_OTEL_PHP_TESTS_GROUP}" ]; then

        if [ "${ELASTIC_OTEL_PHP_TESTS_GROUP}" == "requires_external_services" ]; then
            echo "There no tests in requires_external_services group yet"
            exit 0
        fi

        composer_command=("${composer_command[@]}" --group "${ELASTIC_OTEL_PHP_TESTS_GROUP}")
    fi

    if [ -n "${ELASTIC_OTEL_PHP_TESTS_FILTER}" ]; then
        composer_command=("${composer_command[@]}" --filter "${ELASTIC_OTEL_PHP_TESTS_FILTER}")
    fi

    env | sort

    "${composer_command[@]}" 2>&1 | tee "/elastic_otel_php_tests/logs/composer_-_run_component_tests.log"
}

main "$@"
