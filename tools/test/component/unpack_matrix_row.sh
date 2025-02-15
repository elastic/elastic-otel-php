#!/usr/bin/env bash
set -e -o pipefail
#set -x

this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
this_script_dir="$( realpath "${this_script_dir}" )"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

function is_value_in_array () {
    # The first argument is the element that should be in array
    local value_to_check="$1"
    # The rest of the arguments is the array
    local -a array=( "${@:2}" )

    for current_value in "${array[@]}"; do
        if [ "${value_to_check}" == "${current_value}" ] ; then
            echo "true"
            return
        fi
    done
    echo "false"
}

function assert_value_is_in_array () {
    local is_value_in_array_ret_val
    is_value_in_array_ret_val=$(is_value_in_array "$@")
    if [ "${is_value_in_array_ret_val}" != "true" ] ; then
        exit 1
    fi
}

function convert_test_app_host_kind_short_to_long_name () {
    local shortName="$1"
    case "${shortName}" in
        'cli')
                echo "CLI_script"
                return
                ;;
        'http')
                echo "Builtin_HTTP_server"
                return
                ;;
        *)
                echo "Unknown component tests short app code host kind name: \`${shortName}'"
                exit 1
                ;;
    esac
}

function convert_test_group_short_to_long_name () {
    local shortName="$1"
    case "${shortName}" in
        'no_ext_svc')
                echo "does_not_require_external_services"
                return
                ;;
        'with_ext_svc')
                echo "requires_external_services"
                return
                ;;
        *)
                echo "Unknown component tests short group name: \`${shortName}'"
                exit 1
                ;;
    esac
}

function unpack_row_optional_parts_to_env_vars () {
    local key="$1"
    local value="$2"
    case "${key}" in
        'prod_log_level_syslog')
                export ELASTIC_OTEL_LOG_LEVEL_SYSLOG="${value}"
                ;;
        *)
                echo "Unknown optional part key: \`${key}' (value: \`${value}')"
                exit 1
                ;;
    esac
}

function unpack_row_parts_to_env_vars () {
    #
    # Expected format (see generate_matrix.sh)
    #
    #       php_version,package_type,test_app_host_kind_short_name,test_group[,<optional tail>]
    #       [0]         [1]          [2]                           [3]         [4]
    #
    local matrix_row_as_string="$1"
    if [ -z "${matrix_row_as_string}" ] ; then
        echo "The first mandatory argument (generated matrix row) is missing"
        exit 1
    fi

    local matrix_row_parts
    IFS=',' read -ra matrix_row_parts <<< "${matrix_row_as_string}"

    local php_version_dot_separated=${matrix_row_parts[0]}
    local php_version_no_dot
    php_version_no_dot=$(convert_dot_separated_to_no_dot_version "${php_version_dot_separated}")
    assert_value_is_in_array "${php_version_no_dot}" "${supported_php_versions[@]:?}"
    export ELASTIC_OTEL_PHP_TESTS_PHP_VERSION="${php_version_dot_separated}"

    local package_type=${matrix_row_parts[1]}
    assert_value_is_in_array "${package_type}" "${supported_package_types[@]:?}"
    export ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE="${package_type}"

    local test_app_code_host_kind_short_name=${matrix_row_parts[2]}
    assert_value_is_in_array "${test_app_code_host_kind_short_name}" "${test_app_code_host_kinds_short_names[@]:?}"
    local test_app_code_host_kind
    test_app_code_host_kind=$(convert_test_app_host_kind_short_to_long_name "${test_app_code_host_kind_short_name}")
    export ELASTIC_OTEL_PHP_TESTS_APP_CODE_HOST_KIND="${test_app_code_host_kind}"

    local test_group_short_name=${matrix_row_parts[3]}
    assert_value_is_in_array "${test_group_short_name}" "${test_groups_short_names[@]:?}"
    local test_group
    test_group=$(convert_test_group_short_to_long_name "${test_group_short_name}")
    export ELASTIC_OTEL_PHP_TESTS_GROUP="${test_group}"

    for optional_part in "${matrix_row_parts[@]:4}" ; do
        IFS='=' read -ra optional_part_key_value <<< "${optional_part}"
        unpack_row_optional_parts_to_env_vars "${optional_part_key_value[0]}" "${optional_part_key_value[1]}"
    done
}

unpack_row_parts_to_env_vars "$@"
