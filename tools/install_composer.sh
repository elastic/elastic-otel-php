#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function on_script_exit() {
    local exit_code=$?

    if [ -n "${temp_dir+x}" ] && [ -d "${temp_dir}" ]; then
        delete_temp_dir "${temp_dir}"
    fi

    exit ${exit_code}
}

function main() {
    # The latest versions listed at https://getcomposer.org/download/#manual-download
    # 2.9.1 released on 2025-11-13
    local composer_version_to_install=2.9.1

    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    trap on_script_exit EXIT

    # Code below was taken from https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md

    temp_dir="$(mktemp -d)"

    pushd "${temp_dir}" || exit 1

    local expected_checksum
    expected_checksum="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"

    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

    local actual_checksum
    actual_checksum="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [[ "${expected_checksum}" != "${actual_checksum}" ]]; then
        >&2 echo "ERROR: Invalid installer checksum (expected: ${expected_checksum}, actual: ${actual_checksum})"
        popd
        exit 1
    fi

    php composer-setup.php --quiet --filename=composer --install-dir=/usr/local/bin "--version=${composer_version_to_install}"

    popd || exit 1
}

main "$@"
