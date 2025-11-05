#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function show_help() {
    echo "Usage: $0 [optional arguments]"
    echo
    echo "Options:"
    echo "  --keep_temp_files       Optional. Keep temporary files. Default: false (i.e., delete temporary files on both success and failure)."
    echo
    echo "Example:"
    echo "  $0 --keep_temp_files"
}

# Function to parse arguments
function parse_args() {
    export ELASTIC_OTEL_PHP_DEV_KEEP_TEMP_FILES="false"

    while [[ "$#" -gt 0 ]]; do
        case $1 in
        --keep_temp_files)
            export ELASTIC_OTEL_PHP_DEV_KEEP_TEMP_FILES="true"
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown parameter passed: $1"
            show_help
            exit 1
            ;;
        esac
        shift
    done
}

function build_command_to_derive_composer_json_for_prod() {
    # Make some inconsequential change to composer.json just to make the one for dev different from the one for production.
    # So that the hash codes are different and ComposerAutoloaderInit<composer.json hash code> classes defined in vendor/composer/autoload_real.php
    # in the installed package and component tests vendor directories have different names.
    # Note that even though it is `require_once __DIR__ . '/composer/autoload_real.php'` in vendor/autoload.php
    # it does not prevent `Cannot redeclare class` error because those two autoload_real.php files are located in different directories
    # require_once does not help.

    echo "composer --no-interaction --no-scripts --no-update --dev --quiet remove ext-mysqli"
}

function should_remove_not_dev_dep_from_composer_json_for_tests() {
    dep_name="${1:?}"

    local package_prefixes_to_remove_if_present=("open-telemetry/opentelemetry-auto-")
    local packages_to_remove_if_present=("php-http/guzzle7-adapter")
    packages_to_remove_if_present+=("nyholm/psr7-server")
    packages_to_remove_if_present+=("open-telemetry/exporter-otlp")
    packages_to_remove_if_present+=("open-telemetry/sdk")

    for package_prefix_to_remove_if_present in "${package_prefixes_to_remove_if_present[@]}" ; do
        if [[ "${dep_name}" == "${package_prefix_to_remove_if_present}"* ]]; then
            echo "true"
            return
        fi
    done

    for package_to_remove_if_present in "${packages_to_remove_if_present[@]}" ; do
        if [[ "${dep_name}" == "${package_to_remove_if_present}" ]]; then
            echo "true"
            return
        fi
    done

    echo "false"
}

function build_list_of_not_dev_deps_to_remove_from_composer_json_for_tests() {
    mapfile -t present_deps_in_quotes< <(jq '."require" | keys | .[]' "${repo_root_dir}/composer.json")

    local deps_to_remove=()
    for present_dep_in_quotes in "${present_deps_in_quotes[@]}" ; do
        local present_dep="${present_dep_in_quotes%\"}"
        present_dep="${present_dep#\"}"
        present_deps+=("${present_dep}")
        should_remove=$(should_remove_not_dev_dep_from_composer_json_for_tests "${present_dep}")
        if [ "${should_remove}" == "true" ] ; then
            deps_to_remove+=("${present_dep}")
        fi
    done

    if [ ${#deps_to_remove[@]} -eq 0 ]; then
        echo "There should be at least one package to remove to generate composer json derived for tests"
        exit 1
    fi

    echo "${deps_to_remove[*]}"
}

function build_command_to_derive_composer_json_for_tests() {
    # composer json for tests is used in PHPUnit and application code for component tests context
    # so we would like to not have any dependencies that we don't use in tests code and that should be loaded by EDOT package
    # such as open-telemetry/opentelemetry-auto-*, etc.
    # We would like to make sure that those dependencies are loaded by EDOT package and not loaded from tests vendor

    local not_dev_deps_to_remove
    not_dev_deps_to_remove=$(build_list_of_not_dev_deps_to_remove_from_composer_json_for_tests)

    echo "composer --no-scripts --no-update --quiet remove ${not_dev_deps_to_remove}"
}

function build_generated_composer_json_full_path() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local generated_composer_json_file_name
    generated_composer_json_file_name="$(build_generated_composer_json_file_name "${env_kind}" "${PHP_version_no_dot}")"
    echo "${generated_composer_lock_files_stage_dir}/${generated_composer_json_file_name}"
}

function derive_composer_json_for_env_kind() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local base_composer_json_full_path
    base_composer_json_full_path="$(build_generated_composer_json_full_path "dev" "${PHP_version_no_dot}")"

    echo "Deriving composer json for ${env_kind} from ${base_composer_json_full_path} ..."

    local derived_composer_json_full_path
    derived_composer_json_full_path="$(build_generated_composer_json_full_path "${env_kind}" "${PHP_version_no_dot}")"

    local command_to_derive
    case ${env_kind} in
        prod)
            command_to_derive=$(build_command_to_derive_composer_json_for_prod)
            ;;
        tests)
            command_to_derive=$(build_command_to_derive_composer_json_for_tests)
            ;;
        *)
            echo "There is no way to generate derived composer json for environment kind ${env_kind}"
            exit 1
            ;;
    esac

    cp -f "${base_composer_json_full_path}" "${derived_composer_json_full_path}"

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

    echo "Diff between ${base_composer_json_full_path} and ${derived_composer_json_full_path}"
    local has_compared_the_same="true"
    diff "${base_composer_json_full_path}" "${derived_composer_json_full_path}" || has_compared_the_same="false"
    if [ "${has_compared_the_same}" = "true" ]; then
        echo "${base_composer_json_full_path} and ${derived_composer_json_full_path} should be different"
        exit 1
    fi
}

function generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local composer_json_full_path
    composer_json_full_path="$(build_generated_composer_json_full_path "${env_kind}" "${PHP_version_no_dot}")"

    local composer_lock_file_name
    composer_lock_file_name="$(build_generated_composer_lock_file_name "${env_kind}" "${PHP_version_no_dot}")"

    echo "Generating ${composer_lock_file_name} from ${composer_json_full_path} ..."

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    echo "composer_json_full_path: ${composer_json_full_path} ..."
    echo "composer_lock_file_name: ${composer_lock_file_name} ..."

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${generated_composer_lock_files_stage_dir}:/generated_composer_lock_files_stage_dir" \
        -v "${repo_root_stage_dir}/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}:/repo_root/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && COMPOSER= composer run-script -- generate_lock_use_current_json \
            && cp -f /repo_root/composer.lock /generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
            && chown ${current_user_id}:${current_user_group_id} /generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
            && chmod +r,u+w /generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
        "
}

function copy_file() {
    local src_file="${1:?}"
    local dst_file="${2:?}"

    echo "Copying ${src_file} to ${dst_file} ..."
    cp "${src_file}" "${dst_file}"
}

function delete_dir_contents() {
    local dir_contents_to_delete="${1:?}"

    if [[ -d "${dir_contents_to_delete}" ]]; then
        echo "Deleting contents of ${dir_contents_to_delete}/ ..."
        rm -rf "${dir_contents_to_delete:?}/"*
    fi
}

function delete_temp_dir() {
    local dir_to_delete="${1:?}"

    if [[ "${ELASTIC_OTEL_PHP_DEV_KEEP_TEMP_FILES:?}" == "true" ]]; then
        echo "Keeping temporary directory ${dir_to_delete}/"
        return
    fi

    echo "Deleting temporary directory ${dir_to_delete}/ ..."
    rm -rf "${dir_to_delete:?}/"
}

function on_script_exit() {
    if [[ -d "${repo_root_stage_dir}" ]]; then
        delete_temp_dir "${repo_root_stage_dir}"
    fi
}

function main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    current_user_id="$(id -u)"
    current_user_group_id="$(id -g)"

    trap on_script_exit EXIT

    repo_root_stage_dir="$(mktemp -d)"
    copy_file "${repo_root_dir}/composer.json" "${repo_root_stage_dir}/composer.json"

    generated_composer_lock_files_stage_dir="${repo_root_stage_dir}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}"
    mkdir -p "${generated_composer_lock_files_stage_dir}"

    local dev_81_composer_json_full_path
    dev_81_composer_json_full_path="$(build_generated_composer_json_full_path "dev" "81")"

    # composer run-script -- adapt_composer_json_download_and_adapt_packages_to_PHP_81 "${repo_root_stage_dir}" "${dev_81_composer_json_full_path}"
    #   expects
    #       - "${repo_root_stage_dir}/composer.json"
    #   creates
    #       - "${dev_81_composer_json_full_path}"
    #       - "${repo_root_stage_dir}/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}/"
    composer run-script -- adapt_composer_json_download_and_adapt_packages_to_PHP_81 "${repo_root_stage_dir}" "${dev_81_composer_json_full_path}"

    local dev_composer_json_full_path
    dev_composer_json_full_path="$(build_generated_composer_json_full_path "dev" "not 8.1")"
    copy_file "${repo_root_dir}/composer.json" "${dev_composer_json_full_path}"

    echo "ls -al ${repo_root_stage_dir}"
    ls -al "${repo_root_stage_dir}"
    echo "ls -al ${generated_composer_lock_files_stage_dir}"
    ls -al "${generated_composer_lock_files_stage_dir}"

    for env_kind in "prod" "tests"; do
        for PHP_version_no_dot in "81" "not 81"; do
            derive_composer_json_for_env_kind "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "dev" "prod" "tests"; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    mkdir -p "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"
    delete_dir_contents "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"
    cp "${generated_composer_lock_files_stage_dir}/"* "${elastic_otel_php_build_tools_composer_lock_files_dir:?}/"
    # No need for delete_temp_dir "${repo_root_stage_dir}" - ${repo_root_stage_dir} is deleted in on_script_exit()

    composer run-script -- verify_generated_composer_lock_files || true # 'true' always succeeds, preventing script exit
    exit_code=$?
    if [ "${exit_code}" -ne 0 ]; then
        delete_dir_contents "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"
        exit ${exit_code}
    fi
}

main "$@"
