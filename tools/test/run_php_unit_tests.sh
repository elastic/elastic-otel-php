#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${PWD}")"
    source "${repo_root_dir}/tools/shared.sh"

    edot_log "Environment variables matching ELASTIC_OTEL_PHP_TESTS_:"
    (env | grep ELASTIC_OTEL_PHP_TESTS_ | sort) || true

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
