#!/usr/bin/env bash
set -e -o pipefail

# Reverts all EDOT patches from the upstream submodule after running tests.
# Patches are reverted in reverse order from the upstream/ directory.
# Usage: ./elastic_tests/patch/revert.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(realpath "${SCRIPT_DIR}/../..")"
UPSTREAM_DIR="${REPO_ROOT}/upstream"

echo "Reverting EDOT test patches from upstream..."

# Collect patches into array and iterate in reverse order
patches=("${SCRIPT_DIR}"/*.patch)
for (( i=${#patches[@]}-1 ; i>=0 ; i-- )); do
    patch_file="${patches[$i]}"
    if [ -f "$patch_file" ]; then
        echo "  Reverting: $(basename "$patch_file")"
        abs_patch="$(realpath "$patch_file")"
        if git -C "${UPSTREAM_DIR}" apply --reverse --check "$abs_patch" 2>/dev/null; then
            git -C "${UPSTREAM_DIR}" apply --reverse "$abs_patch"
        elif git -C "${UPSTREAM_DIR}" apply --check "$abs_patch" 2>/dev/null; then
            echo "  SKIP (not applied): $(basename "$patch_file")"
        else
            echo "  ERROR: patch failed to revert: $(basename "$patch_file")" >&2
            git -C "${UPSTREAM_DIR}" apply --reverse --check "$abs_patch"
            exit 1
        fi
    fi
done

echo "EDOT test patches reverted."
