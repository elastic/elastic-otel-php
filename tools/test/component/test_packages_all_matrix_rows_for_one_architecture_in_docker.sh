#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function show_help() {
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

function parse_args() {
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

function convert_matrix_row_file_name_suitable_string() {
    local matrix_row=${1:?}
    # Example: 8.4,deb,cli,no_ext_svc,prod_log_level_syslog=TRACE

    local result="${matrix_row}"
    result=${result/,/_}
    result=${result/=/_}

    echo "${result}"
}

function main() {
    local current_workflow_group_name="Setting the environment for ${BASH_SOURCE[0]}"
    echo "::group::${current_workflow_group_name}"

    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/../../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    parse_args "$@"

    ensure_dir_exists_and_empty "${logs_dir}"
    touch "${logs_dir}/z_dummy_file_to_make_directory_non-empty"

    env | sort

    end_github_workflow_log_group "${current_workflow_group_name}"

    local current_workflow_group_name="Testing packages on generated matrix rows one at a time"
    start_github_workflow_log_group "${current_workflow_group_name}"
    while read -r matrix_row ; do
        local logs_sub_dir="${logs_dir}/${matrix_row}"
        "${this_script_dir}/test_packages_one_matrix_row_in_docker.sh" --matrix_row "${matrix_row}" --packages_dir "${packages_dir}" --logs_dir "${logs_sub_dir}"
        echo "$matrix_row"
    done < <("${this_script_dir}/generate_matrix.sh")
    end_github_workflow_log_group "${current_workflow_group_name}"
}

main "$@"
