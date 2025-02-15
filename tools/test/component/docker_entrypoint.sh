#!/usr/bin/env bash
set -xe -o pipefail

this_script_full_path="${BASH_SOURCE[0]}"
this_script_dir="$( dirname "${this_script_full_path}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
this_script_name="$(basename -- "${this_script_full_path}")"
this_script_name="${this_script_name%.*}"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

install_package_file() {
    local package_file_full_path=${1:?}

    local package_file_name_with_ext
    package_file_name_with_ext=$(basename "${package_file_full_path}")
    local package_file_extension
    package_file_extension="${package_file_name_with_ext##*.}"

    case "${package_file_extension}" in
        apk)
            apk add --allow-untrusted "${package_file_full_path}"
            ;;
        deb)
            dpkg -i "${package_file_full_path}"
            ;;
        rpm)
            rpm -ivh "${package_file_full_path}"
            ;;
        *)
            echo "Unknown package file extension: ${package_file_extension}, package_file_full_path: ${package_file_full_path}"
            exit 1
            ;;
    esac
}

function install_elastic_otel_package () {
    # Until we add testing for ARM architecture is hardcoded as x86_64
    local architecture="x86_64"

    local package_file_full_path
    package_file_full_path=$(select_elastic_otel_package_file /elastic_otel_php_tests/packages "${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}" "${architecture}")

    install_package_file "${package_file_full_path}"
}

main() {
    echo "pwd"
    pwd

    echo "ls -l"
    ls -l

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    # Disable agent for auxiliary PHP processes to reduce noise in logs
    export ELASTIC_OTEL_ENABLED=false
    export OTEL_PHP_DISABLED_INSTRUMENTATIONS=all
    export OTEL_PHP_AUTOLOAD_ENABLED=false

    install_elastic_otel_package

    composer install --ignore-platform-req=ext-opentelemetry

    /repo_root/tools/test/component/test_installed_package_one_matrix_row.sh
}

main "$@"
