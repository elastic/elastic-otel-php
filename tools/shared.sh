#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

source "${repo_root_dir:?}/elastic-otel-php.properties"
export elastic_otel_php_version="${version:?}"
export elastic_otel_php_supported_php_versions=("${supported_php_versions[@]:?}")
export elastic_otel_php_supported_package_types=("${supported_package_types[@]:?}")
export elastic_otel_php_test_app_code_host_kinds_short_names=("${test_app_code_host_kinds_short_names[@]:?}")
export elastic_otel_php_test_groups_short_names=("${test_groups_short_names[@]:?}")
export elastic_otel_php_otel_proto_version="${otel_proto_version:?}"
export elastic_otel_php_native_otlp_exporters_based_on_php_impl_version="${native_otlp_exporters_based_on_php_impl_version:?}"

export elastic_otel_php_build_tools_composer_lock_files_dir_name="generated_composer_lock_files"
export elastic_otel_php_build_tools_composer_lock_files_dir="${repo_root_dir:?}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}"

export elastic_otel_php_files_to_mount_in_container=("elastic-otel-php.properties" "phpcs.xml" "phpstan.dist.neon" "phpunit.xml")
export elastic_otel_php_dirs_to_mount_in_container=("prod" "tests" "tools")

# Make sure the following value is in sync with the rest of locations where it's used:
#   - tools/build/AdaptPackagesToPhp81.php
# The path is relative to repo root
export elastic_otel_php_packages_adapted_to_PHP_81_rel_path="build/adapted_to_PHP_81/packages"

# Make sure the following value is in sync with the rest of locations where it's used:
#   - tools/build/AdaptPackagesToPhp81.php
# The path is relative to repo root
export elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path="build/adapted_to_PHP_81/composer_home"

# Make sure the following value is in sync with the rest of locations where it's used:
#   - tools/build/AdaptPackagesToPhp81.php
# The path is relative to repo root
export elastic_otel_php_adapt_to_PHP_81_dirs_used_by_install_rel_path=("${elastic_otel_php_build_tools_composer_lock_files_dir_name}" "prod/php" "tools/build")

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

function build_generated_composer_json_file_name() {
    local env_kind="${1:?}"

    echo "${env_kind}.json"
}

function build_generated_composer_lock_file_name() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    echo "${env_kind}_${PHP_version_no_dot}.lock"
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

    local src_dir_ls
    src_dir_ls=$(ls -A "${src_dir}/")
    if [ -z "${src_dir_ls}" ]; then
        return
    fi

    echo "Copying directory contents ${src_dir}/ to ${dst_dir}/ ..."
    cp -r "${src_dir}/"* "${dst_dir}/"
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

function build_container_volume_mount_options() {
    # $1 should be the name of the environment variable to hold the result
    # local -n makes `result_var' reference to the variable named by $1
    local -n result_var=${1:?}
    result_var=()

    local files_to_mount=("elastic-otel-php.properties" "phpcs.xml" "phpstan.dist.neon" "phpunit.xml")
    for file_to_mount in "${files_to_mount[@]:?}" ; do
        result_var+=(-v "${PWD}/${file_to_mount}:/repo_root/${file_to_mount}:ro")
    done
    local dirs_to_mount=("prod" "tests" "tools")
    for dir_to_mount in "${dirs_to_mount[@]:?}" ; do
        result_var+=(-v "${PWD}/${dir_to_mount}/:/repo_root/${dir_to_mount}/:ro")
    done
}
