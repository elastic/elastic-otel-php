#!/usr/bin/env bash
set -e -o pipefail
#set -x

#
# Expected format
#
#       php_version,package_type,test_app_host_kind_short_name,test_group[,<optional tail>]
#       [0]         [1]          [2]                           [3]
#

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

function generate_rows_to_test_increased_log_level () {
    local php_version_no_dot
    php_version_no_dot=$(get_lowest_supported_php_version)
    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
    local package_type="${test_all_php_versions_with_package_type:?}"
    local test_app_code_host_kind="${elastic_otel_php_test_app_code_host_kinds_short_names[0]:?}"
    local test_group="${elastic_otel_php_test_groups_short_names[0]:?}"
    echo "${php_version_dot_separated},${package_type},${test_app_code_host_kind},${test_group},prod_log_level_syslog=TRACE"

    php_version_no_dot=$(get_highest_supported_php_version)
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
    package_type=apk
    test_app_code_host_kind="${elastic_otel_php_test_app_code_host_kinds_short_names[1]:?}"
    test_group="${elastic_otel_php_test_groups_short_names[1]:?}"
    echo "${php_version_dot_separated},${package_type},${test_app_code_host_kind},${test_group},prod_log_level_syslog=DEBUG"
}

function append_test_app_code_host_kind_and_group () {
    local row_so_far="${1:?}"
    for test_app_code_host_kind_short_name in "${elastic_otel_php_test_app_code_host_kinds_short_names[@]:?}" ; do
        for test_group in "${elastic_otel_php_test_groups_short_names[@]:?}" ; do
            echo "${row_so_far},${test_app_code_host_kind_short_name},${test_group}"
        done
    done
}

function generate_rows_to_test_highest_supported_php_version_with_other_package_types () {
    local package_type_to_exclude="${test_all_php_versions_with_package_type:?}"
    local php_version_no_dot
    php_version_no_dot=$(get_highest_supported_php_version)
    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")

    for package_type in "${elastic_otel_php_supported_package_types[@]:?}" ; do
        if [[ "${package_type}" == "${package_type_to_exclude}" ]] ; then
            continue
        fi
        append_test_app_code_host_kind_and_group "${php_version_dot_separated},${package_type}"
    done
}

function generate_rows_to_test_all_php_versions_with_one_package_type () {
    local package_type="${test_all_php_versions_with_package_type:?}"
    for php_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        local php_version_dot_separated
        php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
        append_test_app_code_host_kind_and_group "${php_version_dot_separated},${package_type}"
    done
}

function main () {
    generate_rows_to_test_all_php_versions_with_one_package_type
    generate_rows_to_test_highest_supported_php_version_with_other_package_types

    generate_rows_to_test_increased_log_level
}

main
