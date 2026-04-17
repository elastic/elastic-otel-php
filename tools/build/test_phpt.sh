#!/bin/bash
set -e -o pipefail

# Thin wrapper — delegates to upstream's test_phpt.sh

echo "Entered ${BASH_SOURCE[0]}"

cd upstream
./tools/build/test_phpt.sh "$@"

echo "Exiting ${BASH_SOURCE[0]}"
