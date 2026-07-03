#!/usr/bin/env bash
set -e -u -o pipefail
#
# Thin wrapper: delegates PHP code building (composer install + php-scoper)
# to the upstream submodule's build_php_code_for_packages.sh.
#
# Upstream handles: composer install, php-scoper namespace prefixing,
# autoloader patching, NOTICE generation, and verification.
# Output lands in upstream/_BUILT/php_code_for_packages/ (scoped/<ver>, not_scoped)
# and upstream/_BUILT/NOTICE, which nfpm.yaml packages.
#

this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
this_script_dir="$(realpath "${this_script_dir}")"
edot_root_dir="$(realpath "${this_script_dir}/../..")"

SKIP_NOTICE=false

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
        --skip_notice)
            SKIP_NOTICE=true
            ;;
        *)
            ;;
        esac
        shift
    done
}

parse_args "$@"

echo "=== EDOT build_php_code_for_packages.sh: delegating to upstream ==="

# Assemble EDOT's own PHP tree into _BUILT/php_code_for_packages/, mirroring how
# upstream assembles prod/php/ into its own _BUILT/php_code_for_packages/:
# top-level .php files copied flat, subdirectories (Elastic/) copied as-is.
# .template sources are excluded — only their generated output should ship.
_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR="${edot_root_dir}/_BUILT/php_code_for_packages"
rm -rf "${_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR}"
mkdir -p "${_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR}"
cp "${edot_root_dir}/elastic_prod/php/"*.php "${_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR}/"
cp -r "${edot_root_dir}/elastic_prod/php/Elastic" "${_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR}/"
find "${_EDOT_BUILT_PHP_CODE_FOR_PACKAGES_DIR}" -name '*.template' -type f -delete

# Configure EDOT-specific PHP templates (e.g., ElasticVendorCustomizations.php)
# before upstream build, which will configure its own templates internally.
"${edot_root_dir}/tools/build/configure_php_templates.sh"

pushd "${edot_root_dir}/upstream"
./tools/build/build_php_code_for_packages.sh "$@"
popd

echo "=== EDOT build_php_code_for_packages.sh: upstream build complete ==="

# Generate the EDOT NOTICE freshly (never mutate a committed file): start from the
# EDOT NOTICE template, then append the upstream-generated package notices.
# Output lands in _BUILT/NOTICE (gitignored, same convention as upstream) which
# nfpm.yaml packages.
if [ "${SKIP_NOTICE}" = "false" ]; then
    mkdir -p "${edot_root_dir}/_BUILT"
    cat "${edot_root_dir}/packaging/NOTICE.template" >"${edot_root_dir}/_BUILT/NOTICE"
    cat "${edot_root_dir}/upstream/_BUILT/NOTICE" >>"${edot_root_dir}/_BUILT/NOTICE"
fi

