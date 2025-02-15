#!/usr/bin/env bash
set -e -o pipefail
set -x

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

show_help() {
    echo "Usage: $0 --architecture <architecture> --packages_dir <full path to a directory> --logs_dir <full path to a directory>"
    echo
    echo "Arguments:"
    echo "  --architecture      Required. Currently only x86_64 is allowed"
    echo "  --packages_dir      Required. Full path to the directory with the built packages to test"
    echo "  --logs_dir          Required. Full path to the directory where generated logs will be stored. NOTE: All existing files in this directory will be deleted"
    echo
    echo "Example:"
    echo "  $0 --architecture x86_64 --packages_dir '/directory/with/packages' --logs_dir '/directory/to/store/logs'"
}

parse_args() {
    echo "arguments: $*"

    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --architecture)
                architecture="$2"
                shift
                ;;
            --packages_dir)
                packages_dir="$2"
                shift
                ;;
            --logs_dir)
                logs_dir="$2"
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

    if [ -z "${architecture}" ] ; then
        echo "<architecture> argument is missing"
        show_help
        exit 1
    else
        if [ "${architecture}" != "x86_64" ]; then
            echo "Currently only x86_64 architecture is allowed; architecture: ${architecture}"
            exit 1
        fi
    fi

    if [ -z "${packages_dir}" ] ; then
        echo "<packages_dir> argument is missing"
        show_help
        exit 1
    fi

    if [ -z "${logs_dir}" ] ; then
        echo "<logs_dir> argument is missing"
        show_help
        exit 1
    fi

    echo "architecture: ${architecture}"
    echo "packages_dir: ${packages_dir}"
    echo "logs_dir: ${logs_dir}"
}

convert_matrix_row_file_name_suitable_string() {
    local matrix_row=${1:?}
    # Example: 8.4,deb,cli,no_ext_svc,prod_log_level_syslog=TRACE

    local result="${matrix_row}"
    result=${result/,/_}
    result=${result/=/_}

    echo "${result}"
}

main() {
    parse_args "$@"

    while read -r matrix_row ; do
        local matrix_row_file_name_suitable_string
        matrix_row_file_name_suitable_string=$(convert_matrix_row_file_name_suitable_string "${matrix_row}")
        local logs_sub_dir="${logs_dir}/${matrix_row_file_name_suitable_string}"
        "${this_script_dir}/test_packages_one_matrix_row_in_docker.sh" --matrix_row "${matrix_row}" --packages_dir "${packages_dir}" --logs_dir "${logs_sub_dir}"
        echo "$matrix_row"
    done < <("${this_script_dir}/generate_matrix.sh")
}

main "$@"
