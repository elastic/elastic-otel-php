#!/bin/bash
#
# Thin wrapper — delegates to upstream's test_otel_unit_tests.sh.
# This script runs inside a docker container where the repo root is mounted at /source.
#

this_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(realpath "${this_script_dir}/../..")"

exec "${repo_root}/upstream/tools/build/test_otel_unit_tests.sh" "$@"
