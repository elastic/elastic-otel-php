#!/bin/bash
#
# Generates PHP files from templates for both EDOT and upstream.
# EDOT templates use elastic-otel-php.properties for version injection.

set -o pipefail
set -e
set -u

echo "Entered ${BASH_SOURCE[0]}"

repo_root_dir="$(dirname "$(dirname "$(dirname "$(realpath "${BASH_SOURCE[0]}")")")")"

# ── EDOT-specific templates ────────────────────────────────────────────
# Read EDOT version from elastic-otel-php.properties and generate
# ElasticVendorCustomizations.php from its .template file.

source "${repo_root_dir}/upstream/tools/read_properties.sh"
read_properties "${repo_root_dir}/elastic-otel-php.properties" _EDOT

configure_file() {
    local _ARG_INPUT_FILE=$1
    while IFS= read -r line; do
        while [[ "$line" =~ @(.*?)@ ]]; do
            var_name="${BASH_REMATCH[1]}"
            var_value="${!var_name}"
            line="${line//@${var_name}@/${var_value}}"
        done
        echo "$line"
    done < "${_ARG_INPUT_FILE}"
}

configure_from_template() {
    local _ARG_INPUT_FILE=$1
    local _ARG_OUTPUT_FILE=${2:-${_ARG_INPUT_FILE%.template}}
    if [[ "${_ARG_INPUT_FILE}" != *.template ]]; then
        echo "configure_from_template error: File name must end with '.template'" >&2
        exit 1
    fi
    echo "Configuring file ${_ARG_OUTPUT_FILE} from ${_ARG_INPUT_FILE}"
    mkdir -p "$(dirname "${_ARG_OUTPUT_FILE}")"
    configure_file "${_ARG_INPUT_FILE}" >"${_ARG_OUTPUT_FILE}"
}

# Output lands directly in _BUILT/php_code_for_packages/ (not next to the .template
# in elastic_prod/php/) so build_php_code_for_packages.sh can assemble the whole
# EDOT PHP tree in one place, the same way upstream assembles its own.
configure_from_template \
    "${repo_root_dir}/elastic_prod/php/Elastic/OTel/ElasticVendorCustomizations.php.template" \
    "${repo_root_dir}/_BUILT/php_code_for_packages/Elastic/OTel/ElasticVendorCustomizations.php"

# ── Upstream templates ──────────────────────────────────────────────────
cd "${repo_root_dir}/upstream"
./tools/build/configure_php_templates.sh

echo "Exiting ${BASH_SOURCE[0]}"
