#!/usr/bin/env bash
set -e -o pipefail

# Thin wrapper — delegates to upstream's test_php_static_and_unit.sh
# running from the upstream/ directory context.

echo "Entered ${BASH_SOURCE[0]}"

cd upstream
./tools/test/test_php_static_and_unit.sh "$@"

echo "Exiting ${BASH_SOURCE[0]}"
