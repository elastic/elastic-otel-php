#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function print_caller_stack_trace() {
    local numberCallStackFrames
    numberCallStackFrames=${#FUNCNAME[@]}
    echo "Call stack (${numberCallStackFrames} frames - most recent on top):"

    local i
    # Stop at 1 to skip the this function itself
    # SC2004: $/${} is unnecessary on arithmetic variables.
    # shellcheck disable=SC2004
    for (( i=0; i<${numberCallStackFrames}; ++i )); do
        local func="${FUNCNAME[$i]}"
        local file="${BASH_SOURCE[$i]}"
        local line="${BASH_LINENO[$((i-1))]}" # BASH_LINENO is off by one index
        echo "    ${file}:${line} ; ${func}()"
    done
    # Add the main script entry point
    echo "    ${BASH_SOURCE[0]}:${BASH_LINENO[0]} main"
}

this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
this_script_dir="$(realpath "${this_script_dir}")"
src_repo_root_dir="$(realpath "${this_script_dir}/..")"

source "${src_repo_root_dir}/elastic-otel-php.properties"
export elastic_otel_php_version="${version:?}"
export elastic_otel_php_supported_php_versions=("${supported_php_versions[@]:?}")
export elastic_otel_php_supported_package_types=("${supported_package_types[@]:?}")
export elastic_otel_php_test_app_code_host_kinds_short_names=("${test_app_code_host_kinds_short_names[@]:?}")
export elastic_otel_php_test_groups_short_names=("${test_groups_short_names[@]:?}")
export elastic_otel_php_otel_proto_version="${otel_proto_version:?}"
export elastic_otel_php_native_otlp_exporters_based_on_php_impl_version="${native_otlp_exporters_based_on_php_impl_version:?}"

# Make sure the following value is in sync with the rest of locations where it's defined:
#   - tools/build/PhpDepsEnvKind.php
export elastic_otel_php_deps_env_kinds=("dev" "prod")

# Make sure the following value is in sync with the rest of locations where it's defined:
#   - tools/build/InstallPhpDeps.php
export elastic_otel_php_generated_composer_lock_files_dir_name="generated_composer_lock_files"

# Make sure the following value is in sync with the rest of locations where it's defined:
#   - tools/build/AdaptPhpDepsTo81.php
# The path is relative to repo root
export elastic_otel_php_packages_adapted_to_PHP_81_rel_path="build/adapted_to_PHP_81/packages"

# Make sure the following value is in sync with the rest of locations where it's defined:
#   - tools/build/AdaptPhpDepsTo81.php
# The path is relative to repo root
export elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path="build/adapted_to_PHP_81/composer_home"

# Make sure the following value is in sync with the rest of locations where it's defined:
#   - tools/build/InstallPhpDeps.php
#   - tests/bootstrapDev.php
export elastic_otel_php_vendor_prod_dir_name="vendor_prod"

function get_supported_php_versions_as_string() {
    local supported_php_versions_as_string=""
    for current_supported_php_version in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        if [[ -n "${supported_php_versions_as_string}" ]]; then # -n is true if string is not empty
            supported_php_versions_as_string="${supported_php_versions_as_string} ${current_supported_php_version}"
        else
            supported_php_versions_as_string="${current_supported_php_version}"
        fi
    done
    echo "${supported_php_versions_as_string}"
}

function get_lowest_supported_php_version() {
    local min_supported_php_version=${elastic_otel_php_supported_php_versions[0]:?}
    for current_supported_php_version in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        ((current_supported_php_version < min_supported_php_version)) && min_supported_php_version=${current_supported_php_version}
    done
    echo "${min_supported_php_version}"
}

function get_highest_supported_php_version() {
    local max_supported_php_version=${elastic_otel_php_supported_php_versions[0]:?}
    for current_supported_php_version in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        ((current_supported_php_version > max_supported_php_version)) && max_supported_php_version=${current_supported_php_version}
    done
    echo "${max_supported_php_version}"
}

function convert_no_dot_to_dot_separated_version() {
    local no_dot_version=${1:?}
    local no_dot_version_str_len=${#no_dot_version}

    if [ "${no_dot_version_str_len}" -ne 2 ]; then
        echo "Dot version should have length 2"
        exit 1
    fi

    echo "${no_dot_version:0:1}.${no_dot_version:1:1}"
}

function convert_dot_separated_to_no_dot_version() {
    local dot_separated_version=${1:?}
    echo "${dot_separated_version/\./}"
}

function adapt_architecture_to_package_type() {
    # architecture must be either arm64 or x86_64 regardless of package_type
    local architecture=${1:?}
    local package_type=${2:?}

    local adapted_arm64
    local adapted_x86_64
    case "${package_type}" in
        apk)
            # Example for package file names:
            #    elastic-otel-php_0.3.0_aarch64.apk
            #    elastic-otel-php_0.3.0_x86_64.apk
            adapted_arm64=aarch64
            adapted_x86_64=x86_64
            ;;
        deb)
            # Example for package file names:
            #    elastic-otel-php_0.3.0_amd64.deb
            #    elastic-otel-php_0.3.0_arm64.deb
            adapted_arm64=arm64
            adapted_x86_64=amd64
            ;;
        rpm)
            # Example for package file names:
            #    elastic-otel-php-0.3.0-1.aarch64.rpm
            #    elastic-otel-php-0.3.0-1.x86_64.rpm
            adapted_arm64=aarch64
            adapted_x86_64=x86_64
            ;;
        *)
            echo "Unknown package type: ${package_type}"
            exit 1
            ;;
    esac

    case "${architecture}" in
        arm64)
            echo "${adapted_arm64}"
            ;;
        x86_64)
            echo "${adapted_x86_64}"
            ;;
        *)
            echo "Unknown architecture: ${architecture}"
            exit 1
            ;;
    esac
}

function select_elastic_otel_package_file() {
    local packages_dir=${1:?}
    local package_type=${2:?}
    # architecture must be either arm64 or x86_64 regardless of package_type
    local architecture=${3:?}

    # Example for package file names:
    #    elastic-otel-php-0.3.0-1.aarch64.rpm
    #    elastic-otel-php-0.3.0-1.x86_64.rpm
    #    elastic-otel-php_0.3.0_aarch64.apk
    #    elastic-otel-php_0.3.0_amd64.deb
    #    elastic-otel-php_0.3.0_arm64.deb
    #    elastic-otel-php_0.3.0_x86_64.apk

    local architecture_adapted_to_package_type
    architecture_adapted_to_package_type=$(adapt_architecture_to_package_type "${architecture}" "${package_type}")

    local found_files=""
    local found_files_count=0
    for current_file in "${packages_dir}"/*"${architecture_adapted_to_package_type}.${package_type}"; do
        if [[ -n "${found_files}" ]]; then # -n is true if string is not empty
            found_files="${found_files} ${current_file}"
        else
            found_files="${current_file}"
        fi
        ((++found_files_count))
    done

    if [ "${found_files_count}" -ne 1 ]; then
        echo "Number of found files should be 1, found_files_count: ${found_files_count}, found_files: ${found_files}"
        exit 1
    fi

    echo "${found_files}"
}

function ensure_dir_exists_and_empty() {
    local dir_to_clean="${1:?}"

    if [ -d "${dir_to_clean}" ]; then
        rm -rf "${dir_to_clean}"
        if [ -d "${dir_to_clean}" ]; then
            echo "Directory ${dir_to_clean} still exists. Directory content:"
            ls -l "${dir_to_clean}"
            exit 1
        fi
    fi

    mkdir -p "${dir_to_clean}"
}

#
# How to use set_set_x_setting and get_current_set_x_setting
#
#   Before the section for which you would like to enable/disable tracing:
#
#       local saved_set_x_setting
#       saved_set_x_setting=$(get_current_set_x_setting)
#       set -x # to enable tracing
#       set +x # to disable tracing
#
#   After the section:
#
#       set_set_x_setting "${saved_set_x_setting}"
#
function get_current_set_x_setting() {
    local current_setting=${-//[^x]/}
    if [[ -n "${current_setting}" ]] ; then
        echo "on"
    else
        echo "off"
    fi
}

function set_set_x_setting() {
    local set_x_setting="$1"
    if [ "${set_x_setting}" == "on" ] ; then
        set -x
    else
        set +x
    fi
}

function start_github_workflow_log_group() {
    local group_name="${1:?}"
    echo "::group::${group_name}"
}

function end_github_workflow_log_group() {
    local group_name="${1:?}"
    echo "::endgroup::${group_name}"
}

function map_env_kind_to_generated_composer_file_name_prefix() {
    local env_kind="${1:?}"

    local base_file_name_prefix="composer"
    case "${env_kind}" in
        "dev")
            echo "${base_file_name_prefix}"
            ;;
        "prod")
            echo "${base_file_name_prefix}_${env_kind}"
            ;;
        *)
            echo "Unknown env_kind: ${env_kind}"
            return 1
            ;;
    esac
}

function build_composer_json_file_name() {
    local env_kind="${1:?}"

    local file_name
    file_name="$(map_env_kind_to_generated_composer_file_name_prefix "${env_kind}")"

    echo "${file_name}.json"
}

function build_generated_composer_lock_file_name() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local file_name_prefix
    file_name_prefix="$(map_env_kind_to_generated_composer_file_name_prefix "${env_kind}")"

    echo "${file_name_prefix}_${PHP_version_no_dot}.lock"
}

function build_light_PHP_docker_image_name_for_version_no_dot() {
    local PHP_version_no_dot="${1:?}"

    local PHP_version_dot_separated
    PHP_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${PHP_version_no_dot}")

    echo "php:${PHP_version_dot_separated}-cli-alpine"
}

function copy_file() {
    local src_file="${1:?}"
    local dst_file="${2:?}"

    echo "Copying file ${src_file} to ${dst_file} ..."
    cp "${src_file}" "${dst_file}"
}

function copy_file_overwrite() {
    local src_file="${1:?}"
    local dst_file="${2:?}"

    echo "Copying file ${src_file} to ${dst_file} ..."
    cp -f "${src_file}" "${dst_file}"
}

function copy_dir_contents() {
    local src_dir="${1:?}"
    local dst_dir="${2:?}"

    echo "Copying directory contents ${src_dir}/ to ${dst_dir}/ ..."
    cp -r "${src_dir}/." "${dst_dir}/"
}

function copy_dir_contents_overwrite() {
    local src_dir="${1:?}"
    local dst_dir="${2:?}"

    local src_dir_ls
    src_dir_ls=$(ls -A "${src_dir}/")
    if [ -z "${src_dir_ls}" ]; then
        return
    fi

    echo "Copying directory contents ${src_dir}/ to ${dst_dir}/ ..."
    cp -r -f "${src_dir}/"* "${dst_dir}/"
}

function delete_dir_contents() {
    local dir_contents_to_delete="${1:?}"

    if [ -d "${dir_contents_to_delete}" ]; then
        echo "Deleting contents of ${dir_contents_to_delete}/ ..."
        rm -rf "${dir_contents_to_delete:?}/"*
    fi
}

function delete_temp_dir() {
    local dir_to_delete="${1:?}"

    if [ -n "${ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES+x}" ] && [ "${ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES}" == "true" ]; then
        echo "Keeping temporary directory ${dir_to_delete}/"
        return
    fi

    echo "Deleting temporary directory ${dir_to_delete}/ ..."
    rm -rf "${dir_to_delete:?}/"
}

function copy_dir_if_exists() {
    local src_dir="${1:?}"
    local dst_dir="${2:?}"

    if [ -d "${src_dir}" ]; then
        mkdir -p "${dst_dir}/"
        copy_dir_contents "${src_dir}" "${dst_dir}"
    fi
}

function should_pass_env_var_to_docker() {
    env_var_name_to_check="${1:?}"

    if [[ ${env_var_name_to_check} == "ELASTIC_OTEL_"* ]] || [[ ${env_var_name_to_check} == "OTEL_"* ]]; then
        echo "true"
        return
    fi

    echo "false"
}

function build_docker_env_vars_command_line_part() {
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
        result_var+=(-e "${env_var_name}=${env_var_value}")
    done < <(env)
}

function build_docker_read_only_volume_mounts_command_line_part() {
    # $1 should be the name of the environment variable to hold the result
    # local -n makes `result_var' reference to the variable named by $1
    # SC2178: Variable was used as an array but is now assigned a string.
    # shellcheck disable=SC2178
    local -n result_var=${1:?}
    result_var=()
    local src_root_dir="${2:?}"
    local dst_root_dir="${3:?}"
    local rel_paths=("${@:4}")
    for rel_path in "${rel_paths[@]:?}" ; do
        result_var+=(-v "${src_root_dir}/${rel_path}:${dst_root_dir}/${rel_path}:ro")
    done
}

function is_valid_php_deps_env_kind() {
    local env_kind_to_check="${1:?}"

    for env_kind in "${elastic_otel_php_deps_env_kinds[@]}" ; do
        if [[ "${env_kind_to_check}" == "${env_kind}" ]]; then
            echo "true"
            return
        fi
    done

    echo "false"
}
