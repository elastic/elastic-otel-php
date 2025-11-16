#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions      Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --logs_dir          Required. Full path to the directory where generated logs will be stored. NOTE: All existing files in this directory will be deleted"
    echo
    echo "Example:"
    echo "  $0 --php_versions '81 82 83 84' --logs_dir '/directory/to/store/logs'"
}

# Function to parse arguments
parse_args() {
    echo "arguments: $*"

    PHP_versions_no_dot=""
    logs_dir=""

    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --php_versions)
                # SC2206: Quote to prevent word splitting/globbing, or split robustly with mapfile or read -a.
                # shellcheck disable=SC2206
                PHP_versions_no_dot=($2)
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

    if [ -z "${PHP_versions_no_dot[*]}" ] ; then
        echo "<php_versions> argument is missing"
        show_help
        exit 1
    fi
    if [ -z "${logs_dir}" ] ; then
        echo "<logs_dir> argument is missing"
        show_help
        exit 1
    fi

    echo "PHP_versions_no_dot: ${PHP_versions_no_dot[*]}"
    echo "logs_dir: ${logs_dir}"
}

main() {
    work_repo_root_dir="$(realpath "${PWD}")"
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/../..")"

    echo "Entered ${BASH_SOURCE[0]}"

    source "${src_repo_root_dir}/tools/shared.sh"

    parse_args "$@"

    ensure_dir_exists_and_empty "${logs_dir}"
    touch "${logs_dir}/z_dummy_file_to_make_directory_non-empty"

    # SC2034: <env var> appears unused. Verify use (or export if used externally).
    # shellcheck disable=SC2034
    ELASTIC_OTEL_PHP_TESTS_LOGS_DIRECTORY="/elastic_otel_php_tests/logs"
    local docker_run_env_vars_cmd_line_args=()
    build_docker_env_vars_command_line_part docker_run_env_vars_cmd_line_args

    # The ${VAR+x} expansion expands to x if VAR is set (even if empty), and to nothing if VAR is unset.
    # This allows you to test for its existence without actually using its value.
    if [[ -n "${GITHUB_SHA+x}" ]]; then
        docker_run_env_vars_cmd_line_args+=(-e "GITHUB_SHA=${GITHUB_SHA}")
    else
        docker_run_env_vars_cmd_line_args+=(-e "GITHUB_SHA=dummy_github_sha")
    fi

    for PHP_version_no_dot in "${PHP_versions_no_dot[@]}"; do
        local PHP_docker_image
        PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

        docker run --rm \
            "${docker_run_env_vars_cmd_line_args[@]}" \
            -v "${work_repo_root_dir}:/docker_host_repo_root:ro" \
            -v "${logs_dir}:/elastic_otel_php_tests/logs" \
            -w "/docker_host_repo_root" \
            "${PHP_docker_image}" \
            sh -c "\
                apk update && apk add bash rsync \
                && /docker_host_repo_root/tools/copy_repo_exclude_generated.sh /docker_host_repo_root /tmp/repo \
                && cd /tmp/repo \
                && ./tools/install_composer.sh \
                && echo 'Running static check on prod (without dev only dependencies); PHP_version_no_dot:' ${PHP_version_no_dot} \
                && ./tools/test/static_check_prod.sh \
                && echo 'Running static check and unit tests; PHP_version_no_dot:' ${PHP_version_no_dot} \
                && ./tools/build/install_PHP_deps_in_dev_env.sh \
                && composer run-script -- static_check_and_run_unit_tests \
            "
    done

    echo "Exiting ${BASH_SOURCE[0]}"
}

main "$@"
