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
        git -C "${UPSTREAM_DIR}" apply --check "$abs_patch" 2>/dev/null || {
            echo "  SKIP (already applied or conflict): $(basename "$patch_file")"
            continue
        }
        git -C "${UPSTREAM_DIR}" apply "$abs_patch"
    fi
done

echo "EDOT test patches applied."
