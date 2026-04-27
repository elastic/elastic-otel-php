#!/bin/bash
#
# Thin wrapper — runs upstream's test_otel_unit_tests_in_docker.sh from
# the upstream/ directory where docker/otel-tests/ and composer.json live.
#

set -e -o pipefail

echo "Entered ${BASH_SOURCE[0]}"

this_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(realpath "${this_script_dir}/../..")"

cd "${repo_root}/upstream"
./tools/build/test_otel_unit_tests_in_docker.sh "$@"

echo "Exiting ${BASH_SOURCE[0]}"
