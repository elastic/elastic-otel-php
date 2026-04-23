#!/usr/bin/env bash
set -e -u -o pipefail
#
# Thin wrapper: delegates PHP dependency building (composer install + php-scoper)
# to the upstream submodule's build_php_deps.sh.
#
# Upstream handles: composer install, php-scoper namespace prefixing,
# autoloader patching, NOTICE generation, and verification.
# Output lands in upstream/prod/php/vendor_XX/ which nfpm.yaml packages.
#

this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
this_script_dir="$(realpath "${this_script_dir}")"
edot_root_dir="$(realpath "${this_script_dir}/../..")"

echo "=== EDOT build_php_deps.sh: delegating to upstream ==="

# Configure EDOT-specific PHP templates (e.g., ElasticVendorCustomizations.php)
# before upstream build, which will configure its own templates internally.
"${edot_root_dir}/tools/build/configure_php_templates.sh"

pushd "${edot_root_dir}/upstream"
./tools/build/build_php_deps.sh "$@"
popd

echo "=== EDOT build_php_deps.sh: upstream build complete ==="
