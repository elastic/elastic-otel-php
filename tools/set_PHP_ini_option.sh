#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    local OPTION_NAME="${1:?}"
    local OPTION_VALUE="${2:?}"

    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    source "${this_script_dir}/shared.sh"

    echo "${OPTION_NAME}=${OPTION_VALUE}" > "/usr/local/etc/php/conf.d/99_custom_config_for_${OPTION_NAME}.ini"

    local ACTUAL_OPTION_VALUE
    ACTUAL_OPTION_VALUE="$(php "${this_script_dir}/set_PHP_ini_option_echo_option_value.php" "${OPTION_NAME}")"
    echo "Option ${OPTION_NAME} value after setting it: ${ACTUAL_OPTION_VALUE}"
}

main "$@"
