#!/usr/bin/env bash
set -e -u -o pipefail
#set -x
#########################################
# TODO: Sergey Kleyman: BEGIN: REMOVE:
###################
set -x
###################
# END: REMOVE
#########################################

function main() {
    env | grep ELASTIC_OTEL_PHP_TESTS_ | sort

    local composer_command=(composer run-script -- run_unit_tests)

    if [ -n "${ELASTIC_OTEL_PHP_TESTS_GROUP+x}" ]; then
        composer_command+=(--group "${ELASTIC_OTEL_PHP_TESTS_GROUP}")
    fi

    if [ -n "${ELASTIC_OTEL_PHP_TESTS_FILTER+x}" ]; then
        composer_command+=(--filter "${ELASTIC_OTEL_PHP_TESTS_FILTER}")
    fi

    "${composer_command[@]}"
}

main "$@"
