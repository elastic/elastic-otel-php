#!/usr/bin/env bash
set -e -o pipefail
#set -x

#
# Expected format
#
#       php_version,package_type,test_app_host_kind_short_name,test_group[,<optional tail>]
#       [0]         [1]          [2]                           [3]
#

function generate_rows_to_test_increased_log_level () {
    local php_version_no_dot
    php_version_no_dot=$(get_array_min_value ${_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS})
    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
    local package_type="${_PROJECT_PROPERTIES_TEST_ALL_PHP_VERSIONS_WITH_PACKAGE_TYPE:?}"
    local -a test_app_code_host_kinds
    IFS=' ' read -r -a test_app_code_host_kinds <<< "$(get_array $_PROJECT_PROPERTIES_TEST_APP_CODE_HOST_KINDS_SHORT_NAMES)"
    local test_app_code_host_kind="${test_app_code_host_kinds[0]}"
    local -a test_groups
    IFS=' ' read -r -a test_groups <<< "$(get_array $_PROJECT_PROPERTIES_TEST_GROUPS_SHORT_NAMES)"
    local test_group="${test_groups[0]}"
    echo "${php_version_dot_separated},${package_type},${test_app_code_host_kind},${test_group},prod_log_level_syslog=TRACE"

    php_version_no_dot=$(get_array_max_value ${_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS})
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
    package_type=apk
    test_app_code_host_kind="${test_app_code_host_kinds[1]}"

    test_group="${test_groups[1]}"
    echo "${php_version_dot_separated},${package_type},${test_app_code_host_kind},${test_group},prod_log_level_syslog=DEBUG"
}

function append_test_app_code_host_kind_and_group () {
    local row_so_far="${1:?}"
    for test_app_code_host_kind_short_name in $(get_array $_PROJECT_PROPERTIES_TEST_APP_CODE_HOST_KINDS_SHORT_NAMES) ; do
        for test_group in $(get_array $_PROJECT_PROPERTIES_TEST_GROUPS_SHORT_NAMES) ; do
            echo "${row_so_far},${test_app_code_host_kind_short_name},${test_group}"
        done
    done
}

function generate_rows_to_test_highest_supported_php_version_with_other_package_types () {
    local package_type_to_exclude="${_PROJECT_PROPERTIES_TEST_ALL_PHP_VERSIONS_WITH_PACKAGE_TYPE:?}"
    local php_version_no_dot
    php_version_no_dot=$(get_array_max_value ${_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS})
    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")

    for package_type in $(get_array $_PROJECT_PROPERTIES_SUPPORTED_PACKAGE_TYPES) ; do
        if [[ "${package_type}" == "${package_type_to_exclude}" ]] ; then
            continue
        fi
        append_test_app_code_host_kind_and_group "${php_version_dot_separated},${package_type}"
    done
}

function generate_rows_to_test_all_php_versions_with_one_package_type () {
    local package_type="${_PROJECT_PROPERTIES_TEST_ALL_PHP_VERSIONS_WITH_PACKAGE_TYPE:?}"
    for php_version_no_dot in $(get_array $_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS) ; do
        local php_version_dot_separated
        php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")
        append_test_app_code_host_kind_and_group "${php_version_dot_separated},${package_type}"
    done
}

function main () {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    source "${repo_root_dir}/tools/helpers/array_helpers.sh"

    source "${repo_root_dir}/upstream/tools/read_properties.sh"
    read_properties "${repo_root_dir}/upstream/project.properties" _PROJECT_PROPERTIES






    generate_rows_to_test_all_php_versions_with_one_package_type
    generate_rows_to_test_highest_supported_php_version_with_other_package_types

    generate_rows_to_test_increased_log_level
}

main "$@"
