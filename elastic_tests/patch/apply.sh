#!/usr/bin/env bash
set -e -o pipefail

# Applies all EDOT patches to the upstream submodule before running tests.
# Patches are applied from the upstream/ directory.
# Usage: ./elastic_tests/patch/apply.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(realpath "${SCRIPT_DIR}/../..")"
UPSTREAM_DIR="${REPO_ROOT}/upstream"

echo "Applying EDOT test patches to upstream..."

for patch_file in "${SCRIPT_DIR}"/*.patch; do
    if [ -f "$patch_file" ]; then
        echo "  Applying: $(basename "$patch_file")"
        abs_patch="$(realpath "$patch_file")"
        if git -C "${UPSTREAM_DIR}" apply --check "$abs_patch" 2>/dev/null; then
            git -C "${UPSTREAM_DIR}" apply "$abs_patch"
        elif git -C "${UPSTREAM_DIR}" apply --reverse --check "$abs_patch" 2>/dev/null; then
            echo "  SKIP (already applied): $(basename "$patch_file")"
        else
            echo "  ERROR: patch failed to apply: $(basename "$patch_file")" >&2
            git -C "${UPSTREAM_DIR}" apply --check "$abs_patch"
            exit 1
        fi
    fi
done

echo "EDOT test patches applied."
