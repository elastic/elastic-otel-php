#!/usr/bin/env bash
set -xe -o pipefail

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

show_help() {
    echo "Usage: $0 --matrix_row <matrix row> --packages_path <full path to a directory> --logs_path <full path to a directory>"
    echo
    echo "Arguments:"
    echo "  --matrix_row        Required. See ./generate_matrix.sh"
    echo "  --packages_path     Required. Full path to the directory with the built packages to test"
    echo "  --logs_path         Required. Full path to the directory where generated logs will be stored. NOTE: All existing files in this directory will be deleted"
    echo
    echo "Example:"
    echo "  $0 --matrix_row '8.4,deb,cli,no_ext_svc,prod_log_level_syslog=TRACE' --packages_path '/directory/with/packages' --logs_path '/directory/to/store/logs'"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --matrix_row)
                matrix_row="$2"
                shift
                ;;
            --packages_path)
                packages_path="$2"
                shift
                ;;
            --logs_path)
                logs_path="$2"
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                echo "Unknown parameter passed: $1"
                show_help
                exit 1
                ;;
        esac
        shift
    done

    if [ -z "${matrix_row}" ] ; then
        echo "<matrix_row> argument is missing"
        show_help
        exit 1
    fi
    if [ -z "${packages_path}" ] ; then
        echo "<packages_path> argument is missing"
        show_help
        exit 1
    fi
    if [ -z "${logs_path}" ] ; then
        echo "<logs_path> argument is missing"
        show_help
        exit 1
    fi
}

ensure_dir_exists_and_empty() {
    local dir_to_clean="${1:?}"

    if [ -d "${dir_to_clean}" ]; then
        rm -rf "${dir_to_clean}"
        if [ -d "${dir_to_clean}" ]; then
            echo "Directory ${dir_to_clean} still exists. Directory content:"
            ls -l "${dir_to_clean}"
            exit 1
        fi
    else
        mkdir -p "${dir_to_clean}"
    fi
}

main() {
    #########################################
    # TODO: Sergey Kleyman: BEGIN: REMOVE:
    ###################
    echo "arguments: $*"
    ###################
    # END: REMOVE
    #########################################

    parse_args "$@"

    echo "matrix_row: ${matrix_row}"
    echo "packages_path: ${packages_path}"
    echo "logs_path: ${logs_path}"

    echo "Current directory: ${PWD}"

    echo "Content of ./build:"
    ls -l -R "./build" || true

    if [ ! -d "${packages_path}" ]; then
        echo "Directory ${packages_path} does not exists"
        exit 1
    fi
    echo "Content of ${packages_path}:"
    ls -l -R "${packages_path}"

    ensure_dir_exists_and_empty "${logs_path}"
    touch "${logs_path}/z_dummy_file_to_make_directory_non-empty"

#    local matrix_row=${1:?}

# TODO: Sergey Kleyman: UNCOMMENT
#    source "${this_script_dir}/unpack_matrix_row.sh" "${matrix_row}"
}

main "$@"
