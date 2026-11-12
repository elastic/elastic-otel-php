#!/bin/bash
#
# Thin wrapper — runs upstream's verify_composer_version_is_pinned.sh from
# the upstream/ directory, where project.properties and the tooling files live.
#

set -e -o pipefail

echo "Entered ${BASH_SOURCE[0]}"

this_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(realpath "${this_script_dir}/../..")"

cd "${repo_root}/upstream"
./tools/build/verify_composer_version_is_pinned.sh "$@"

echo "Exiting ${BASH_SOURCE[0]}"
