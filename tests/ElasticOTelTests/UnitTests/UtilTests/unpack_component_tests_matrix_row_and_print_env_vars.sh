#!/usr/bin/env bash
set -xe -o pipefail

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
repo_root_dir="$( realpath "${this_script_dir}/../../../.." )"

source "${repo_root_dir}/tools/test/unpack_component_tests_matrix_row.sh" "$@" &> /dev/null

env | grep ELASTIC_OTEL_PHP_TESTS_ 2> /dev/null
