#!/usr/bin/env bash
set -e -o pipefail
#set -x


function _assert_value_is_in_array () {
    local is_value_in_array_ret_val
    is_value_in_array_ret_val=$(is_value_in_array "$@")
    if [ "${is_value_in_array_ret_val}" != "true" ] ; then
        echo "Assertion failed: $1 is not in array ${*:2}"
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

function _unpack_row_optional_parts_to_env_vars () {
    local key="$1"
    local value="$2"
    case "${key}" in
        'prod_log_level_syslog')
                export OTEL_PHP_LOG_LEVEL_SYSLOG="${value}"
                ;;
        *)
                echo "Unknown optional part key: \`${key}' (value: \`${value}')"
                exit 1
                ;;
    esac
}

function _export_var_to_env () {
    local prefix="$1"
    local key="$2"
    local value="$3"
    local verbose=$4
    local var_name="${prefix}_$(echo $key | tr '[:lower:]' '[:upper:]')"
    if [ "${verbose}" == "true" ] ; then
        echo "Exporting env var: ${var_name}=${value}"
    fi
    export "$var_name"="$value"
}

#usage: unpack_matrix_row <matrix_row_as_string> [<variable_prefix>] [<verbose>]
function unpack_matrix_row {
    local matrix_row_as_string="$1"
    local variable_prefix="$2"
    local verbose=$3

    if [ -z "${matrix_row_as_string}" ] ; then
        echo "<matrix_row_as_string> argument is missing"
        exit 1
    fi

    if [ -z "${variable_prefix}" ] ; then
        variable_prefix="OTEL_PHP_TESTS"
    fi

    if [ -z "${verbose}" ] ; then
        verbose="false"
    fi


    source "tools/shared.sh"
    source "tools/helpers/array_helpers.sh"
    source "upstream/tools/read_properties.sh"
    read_properties "upstream/project.properties" "_PROJECT_PROPERTIES"

    local matrix_row_parts
    IFS=',' read -ra matrix_row_parts <<< "${matrix_row_as_string}"

    local php_version_dot_separated=${matrix_row_parts[0]}
    local php_version_no_dot
    php_version_no_dot=$(convert_dot_separated_to_no_dot_version "${php_version_dot_separated}")
    _assert_value_is_in_array "${php_version_no_dot}" $(get_array $_PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS)
    _export_var_to_env "${variable_prefix}" "PHP_VERSION" "${php_version_dot_separated}" "${verbose}"

    local package_type=${matrix_row_parts[1]}
    _assert_value_is_in_array "${package_type}"  $(get_array $_PROJECT_PROPERTIES_SUPPORTED_PACKAGE_TYPES)
    _export_var_to_env "${variable_prefix}" "PACKAGE_TYPE" "${package_type}" "${verbose}"

    local test_app_code_host_kind_short_name=${matrix_row_parts[2]}
    _assert_value_is_in_array "${test_app_code_host_kind_short_name}" $(get_array $_PROJECT_PROPERTIES_TEST_APP_CODE_HOST_KINDS_SHORT_NAMES)
    local test_app_code_host_kind
    test_app_code_host_kind=$(convert_test_app_host_kind_short_to_long_name "${test_app_code_host_kind_short_name}")
    _export_var_to_env "${variable_prefix}" "APP_CODE_HOST_KIND" "${test_app_code_host_kind}" "${verbose}"

    local test_group_short_name=${matrix_row_parts[3]}
    _assert_value_is_in_array "${test_group_short_name}" $(get_array $_PROJECT_PROPERTIES_TEST_GROUPS_SHORT_NAMES)
    local test_group
    test_group=$(convert_test_group_short_to_long_name "${test_group_short_name}")
    _export_var_to_env "${variable_prefix}" "GROUP" "${test_group}" "${verbose}"

    for optional_part in "${matrix_row_parts[@]:4}" ; do
        IFS='=' read -ra optional_part_key_value <<< "${optional_part}"
        _unpack_row_optional_parts_to_env_vars "${optional_part_key_value[0]}" "${optional_part_key_value[1]}"
    done
}
