#!/bin/bash
#
# Thin wrapper — delegates to upstream's configure_php_templates.sh
# If EDOT-specific .template files are added in elastic/, configure them here first.

set -o pipefail
set -e
set -u

echo "Entered ${BASH_SOURCE[0]}"

# No EDOT-specific templates at this time.
# If elastic/php/**/*.template files are added, configure them here
# using EDOT project properties before calling upstream.

cd upstream
./tools/build/configure_php_templates.sh

echo "Exiting ${BASH_SOURCE[0]}"
