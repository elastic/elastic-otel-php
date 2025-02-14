#!/usr/bin/env bash
set -xe -o pipefail

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../.." )"
source "${repo_root_dir}/tools/shared.sh"

show_help() {
    echo "Usage: $0 --matrix_row <matrix row> --packages_path <full path to directory with built packages to test>"
    echo
    echo "Arguments:"
    echo "  --matrix_row        Required. See ./generate_component_tests_matrix.sh"
    echo "  --packages_path     Required. Full path to directory with built packages to test"
    echo
    echo "Example:"
    echo "  $0 --matrix_row '8.1,deb,cli,no_ext_svc,prod_log_level_syslog=TRACE' --packages_path '/some/directory'"
}

main() {
    #########################################
    # TODO: Sergey Kleyman: BEGIN: REMOVE:
    ###################
    echo "arguments: $*"
    ###################
    # END: REMOVE
    #########################################

#    local matrix_row=${1:?}

# TODO: Sergey Kleyman: UNCOMMENT
#    source "${this_script_dir}/unpack_component_tests_matrix_row.sh" "${matrix_row}"
}

main "$@"
