#!/usr/bin/env bash
set -e -o pipefail
#set -x

source "${repo_root_dir:?}/elastic-otel-php.properties"
export elastic_otel_php_version="${version:?}"
export elastic_otel_php_supported_php_versions=("${supported_php_versions[@]:?}")
export elastic_otel_php_supported_package_types=("${supported_package_types[@]:?}")
export elastic_otel_php_test_app_code_host_kinds_short_names=("${test_app_code_host_kinds_short_names[@]:?}")
export elastic_otel_php_test_groups_short_names=("${test_groups_short_names[@]:?}")
export elastic_otel_php_otel_proto_version="${otel_proto_version:?}"
export elastic_otel_php_native_otlp_exporters_based_on_php_impl_version="${native_otlp_exporters_based_on_php_impl_version:?}"

export elastic_otel_php_build_tools_composer_lock_files_dir="${repo_root_dir:?}/generated_composer_lock_files"
export elastic_otel_php_build_tools_composer_json_for_dev_file_name="dev.json"
export elastic_otel_php_build_tools_composer_json_for_prod_file_name="prod.json"
export elastic_otel_php_build_tools_composer_json_for_tests_file_name="tests.json"

function get_supported_php_versions_as_string() {
    local supported_php_versions_as_string
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

    local found_files
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

function build_composer_lock_file_name_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    echo "${env_kind}_${PHP_version_no_dot}.lock"
}

build_light_PHP_docker_image_name_for_version_no_dot() {
    local PHP_version_no_dot="${1:?}"

    local PHP_version_dot_separated
    PHP_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${PHP_version_no_dot}")

    echo "php:${PHP_version_dot_separated}-cli-alpine"
}

build_command_to_derive_composer_json_for_prod() {
    # Make some inconsequential change to composer.json just to make the one for dev different from the one for production.
    # So that the hash codes are different and ComposerAutoloaderInit<composer.json hash code> classes defined in vendor/composer/autoload_real.php
    # in the installed package and component tests vendor directories have different names.
    # Note that even though it is `require_once __DIR__ . '/composer/autoload_real.php'` in vendor/autoload.php
    # it does not prevent `Cannot redeclare class` error because those two autoload_real.php files are located in different directories
    # require_once does not help.

    echo "composer --no-scripts --no-update --dev --quiet remove ext-mysqli"
}

should_remove_package_from_composer_json_for_tests() {
    package_to_check="${1:?}"

    local package_prefixes_to_remove_if_present=("open-telemetry/opentelemetry-auto-")
    local packages_to_remove_if_present=("open-telemetry/exporter-otlp")
    packages_to_remove_if_present+=("open-telemetry/sdk")
    packages_to_remove_if_present+=("php-http/guzzle7-adapter")
    packages_to_remove_if_present+=("nyholm/psr7-server")

    for package_prefix_to_remove_if_present in "${package_prefixes_to_remove_if_present[@]}" ; do
        if [[ "${package_to_check}" == "${package_prefix_to_remove_if_present}"* ]]; then
            echo "true"
            return
        fi
    done

    for package_to_remove_if_present in "${packages_to_remove_if_present[@]}" ; do
        if [[ "${package_to_check}" == "${package_to_remove_if_present}" ]]; then
            echo "true"
            return
        fi
    done

    echo "false"
}

build_command_to_derive_composer_json_for_tests() {
    # composer json for tests is used in PHPUnit and application code for component tests context
    # so we would like to not have any dependencies that we don't use in tests code and that should be loaded by EDOT package
    # such as open-telemetry/opentelemetry-auto-*, etc.
    # We would like to make sure that those dependencies are loaded by EDOT package and not loaded from tests vendor

    mapfile -t list_of_prod_packages_in_quotes< <(jq '."require" | keys | .[]' "${repo_root_dir}/composer.json")

    local list_of_prod_packages=()
    for prod_package_in_quotes in "${list_of_prod_packages_in_quotes[@]:?}" ; do
        local prod_package="${prod_package_in_quotes%\"}"
        prod_package="${prod_package#\"}"
        list_of_prod_packages+=("${prod_package}")
    done

    local prod_packages_to_remove=()
    for prod_package in "${list_of_prod_packages[@]:?}" ; do
        should_remove=$(should_remove_package_from_composer_json_for_tests "${prod_package}")
        if [ "${should_remove}" == "true" ] ; then
            prod_packages_to_remove+=("${prod_package}")
        fi
    done

    if [ ${#prod_packages_to_remove[@]} -eq 0 ]; then
        echo "There should be at least one package to remove to generate composer json derived for tests"
        exit 1
    fi

    echo "composer --no-scripts --no-update --quiet remove ${prod_packages_to_remove[*]}"
}

function build_command_to_derive_composer_json() {
    local env_kind="${1:?}"

    case ${env_kind} in
        prod)
            build_command_to_derive_composer_json_for_prod
            ;;
        tests)
            build_command_to_derive_composer_json_for_tests
            ;;
        *)
            echo "There is no way to generate derived composer json for environment kind ${env_kind}"
            exit 1
            ;;
    esac
}

function build_derived_composer_json_full_path() {
    local env_kind="${1:?}"
    local result_dir_full_path="${2:?}"

    local derived_composer_json_file_name
    case ${env_kind} in
        prod)
            derived_composer_json_file_name="${elastic_otel_php_build_tools_composer_json_for_prod_file_name}"
            ;;
        tests)
            derived_composer_json_file_name="${elastic_otel_php_build_tools_composer_json_for_tests_file_name}"
            ;;
        *)
            echo "There is no way to generate derived composer json for environment kind ${env_kind}"
            exit 1
            ;;
    esac

    echo "${result_dir_full_path}/${derived_composer_json_file_name}"
}

function generate_derived_composer_json() {
    local original_composer_json_copy_full_path="${1:?}"
    local env_kind="${2:?}"
    local derived_composer_json_full_path="${3:?}"

    local command_to_derive
    command_to_derive="$(build_command_to_derive_composer_json "${env_kind}")"

    cp -f "${original_composer_json_copy_full_path}" "${derived_composer_json_full_path}"

    local current_user_id
    current_user_id="$(id -u)"
    local current_user_group_id
    current_user_group_id="$(id -g)"

    local lowest_supported_php_version_no_dot
    lowest_supported_php_version_no_dot=$(get_lowest_supported_php_version)
    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${lowest_supported_php_version_no_dot}")

    docker run --rm \
        -v "${derived_composer_json_full_path}:/repo_root/composer.json" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && ${command_to_derive} \
            && chown ${current_user_id}:${current_user_group_id} composer.json \
            && chmod +r,u+w composer.json \
        "
}
