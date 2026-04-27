#!/usr/bin/env bash
set -e -o pipefail

# Thin wrapper — delegates to upstream's test_packages_one_matrix_row_in_docker.sh
# Workaround: in a git submodule .git is a file, not a directory.
# Upstream's verify_running_from_repo_root() checks [ -d .git ] which fails.
# We temporarily replace the .git file with a directory so the check passes.

echo "Entered ${BASH_SOURCE[0]}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"

# Apply EDOT test patches to upstream before running tests
"${REPO_ROOT}/elastic_tests/patch/apply.sh"

cd upstream

cleanup() {
    if [ -f .git_file_backup ]; then
        rm -rf .git
        mv .git_file_backup .git
    fi
    # Revert EDOT test patches after tests (pass or fail)
    "${REPO_ROOT}/elastic_tests/patch/revert.sh"
}
trap cleanup EXIT

mv .git .git_file_backup
mkdir .git

./tools/test/component/test_packages_one_matrix_row_in_docker.sh "$@"

echo "Exiting ${BASH_SOURCE[0]}"
