#!/usr/bin/env bash
set -e -o pipefail
#set -x

source "${repo_root_dir:?}/elastic-otel-php.properties"

get_lowest_supported_php_version() {
    local min_supported_php_version=${supported_php_versions[0]:?}
    for current_supported_php_version in "${supported_php_versions[@]:?}" ; do
        ((current_supported_php_version < min_supported_php_version)) && min_supported_php_version=${current_supported_php_version}
    done
    echo "${min_supported_php_version}"
}

get_highest_supported_php_version() {
    local max_supported_php_version=${supported_php_versions[0]:?}
    for current_supported_php_version in "${supported_php_versions[@]:?}" ; do
        ((current_supported_php_version > max_supported_php_version)) && max_supported_php_version=${current_supported_php_version}
    done
    echo "${max_supported_php_version}"
}

convert_no_dot_to_dot_separated_version() {
    local no_dot_version=${1:?}
    local no_dot_version_str_len=${#no_dot_version}

    if [ "${no_dot_version_str_len}" -ne 2 ]; then
        echo "Dot version should "
        exit 1
    fi

    echo "${no_dot_version:0:1}.${no_dot_version:1:1}"
}
