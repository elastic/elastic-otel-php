#!/usr/bin/env bash
set -xe -o pipefail

echo "::group::source ./tools/shared.sh"
this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"
echo "::endgroup::"

show_help() {
    echo "Usage: $0 --matrix_row <matrix row> --packages_dir <full path to a directory> --logs_dir <full path to a directory>"
    echo
    echo "Arguments:"
    echo "  --matrix_row        Required. See ./generate_matrix.sh"
    echo "  --packages_dir      Required. Full path to the directory with the built packages to test"
    echo "  --logs_dir          Required. Full path to the directory where generated logs will be stored. NOTE: All existing files in this directory will be deleted"
    echo
    echo "Example:"
    echo "  $0 --matrix_row '8.4,deb,cli,no_ext_svc,prod_log_level_syslog=TRACE' --packages_dir '/directory/with/packages' --logs_dir '/directory/to/store/logs'"
}

parse_args() {
    echo "arguments: $*"

    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --matrix_row)
                matrix_row="$2"
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

    if [ -z "${matrix_row}" ] ; then
        echo "<matrix_row> argument is missing"
        show_help
        exit 1
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

    echo "matrix_row: ${matrix_row}"
    echo "packages_dir: ${packages_dir}"
    echo "logs_dir: ${logs_dir}"
}

function should_pass_env_var_to_docker () {
    env_var_name_to_check="${1:?}"

    if [[ ${env_var_name_to_check} == "ELASTIC_OTEL_PHP_TESTS_"* ]]; then
        echo "true"
        return
    fi

    echo "false"
}

function build_docker_env_vars_command_line_part () {
    # $1 should be the name of the environment variable to hold the result
    # local -n makes `result_var' reference to the variable named by $1
    local -n result_var=${1:?}
    result_var=()
    # Iterate over environment variables
    # The code is copied from https://stackoverflow.com/questions/25765282/bash-loop-through-variables-containing-pattern-in-name
    while IFS='=' read -r env_var_name env_var_value ; do
        should_pass=$(should_pass_env_var_to_docker "${env_var_name}")
        if [ "${should_pass}" == "false" ] ; then
            continue
        fi
        echo "Passing env var to docker: name: ${env_var_name}, value: ${env_var_value}"
        result_var=("${result_var[@]}" -e "${env_var_name}=${env_var_value}")
    done < <(env)
}

function select_Dockerfile_based_on_package_type () {
    package_type="${1:?}"

    echo "Dockerfile_${package_type}"
}

main() {
    echo "::group::Preparing to build docker image"
    parse_args "$@"

    echo "Current directory: ${PWD}"

    if [ ! -d "${packages_dir}" ]; then
        echo "Directory ${packages_dir} does not exists"
        exit 1
    fi
    echo "Content of ${packages_dir}:"
    ls -l -R "${packages_dir}"

    ensure_dir_exists_and_empty "${logs_dir}"
    touch "${logs_dir}/z_dummy_file_to_make_directory_non-empty"

    export ELASTIC_OTEL_PHP_TESTS_MATRIX_ROW="${matrix_row}"
    source "${this_script_dir}/unpack_matrix_row.sh" "${ELASTIC_OTEL_PHP_TESTS_MATRIX_ROW:?}"
    env | grep ELASTIC_OTEL_PHP_TESTS_ | sort

    build_docker_env_vars_command_line_part docker_run_cmd_line_args

    docker_run_cmd_line_args=("${docker_run_cmd_line_args[@]}" -v "${packages_dir}:/elastic_otel_php_tests/packages:ro")
    docker_run_cmd_line_args=("${docker_run_cmd_line_args[@]}" -v "${logs_dir}:/elastic_otel_php_tests/logs")
    echo "docker_run_cmd_line_args: ${docker_run_cmd_line_args[*]}"

    local dockerfile
    dockerfile=$(select_Dockerfile_based_on_package_type "${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}")
    echo "Selected Dockerfile: ${dockerfile}"

    local docker_image_tag="elastic-otel-php-tests-component-${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}-${ELASTIC_OTEL_PHP_TESTS_PHP_VERSION:?}"
    echo "::endgroup::"
    echo "::group::Building docker image"
    docker build --file "${this_script_dir}/${dockerfile}" --build-arg "PHP_VERSION=${ELASTIC_OTEL_PHP_TESTS_PHP_VERSION:?}" --tag "${docker_image_tag}" .
    echo "::endgroup::"
    docker run --rm --tty "${docker_run_cmd_line_args[@]}" "${docker_image_tag}"
}

main "$@"
