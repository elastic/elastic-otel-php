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
    export ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES="false"

    while [[ "$#" -gt 0 ]]; do
        case $1 in
        --keep_temp_files)
            export ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES="true"
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            edot_log "Unknown parameter passed: $1"
            show_help
            exit 1
            ;;
        esac
        shift
    done
}

function build_command_to_derive_for_prod() {
    # Make some inconsequential change to composer.json just to make the one for dev different from the one for production.
    # So that the hash codes are different and ComposerAutoloaderInit<composer.json hash code> classes defined in vendor/composer/autoload_real.php
    # in the installed package and component tests vendor directories have different names.
    # Note that even though it is `require_once __DIR__ . '/composer/autoload_real.php'` in vendor/autoload.php
    # it does not prevent `Cannot redeclare class` error because those two autoload_real.php files are located in different directories
    # require_once does not help.

    echo "composer --no-interaction --no-scripts --no-update --dev --quiet remove ext-mysqli"
}

function should_keep_dev_dep_for_prod_static_check() {
    local dep_name="${1:?}"

    local package_prefixes_to_keep=("php-parallel-lint/")
    package_prefixes_to_keep+=("phpstan/")

    local packages_to_keep+=("slevomat/coding-standard")
    package_prefixes_to_keep+=("squizlabs/php_codesniffer")

    for package_prefix_to_keep in "${package_prefixes_to_keep[@]}" ; do
        if [[ "${dep_name}" == "${package_prefix_to_keep}"* ]]; then
            echo "true"
            return
        fi
    done

    for package_to_keep in "${packages_to_keep[@]}" ; do
        if [[ "${dep_name}" == "${package_to_keep}" ]]; then
            echo "true"
            return
        fi
    done

    echo "false"
}

function build_list_of_dev_deps_to_remove_for_prod_static_check() {
    local base_composer_json_full_path="${1:?}"

    mapfile -t present_deps_in_quotes< <(jq '."require-dev" | keys | .[]' "${base_composer_json_full_path}")

    local deps_to_remove=()
    for present_dep_in_quotes in "${present_deps_in_quotes[@]}" ; do
        local present_dep="${present_dep_in_quotes%\"}"
        present_dep="${present_dep#\"}"
        present_deps+=("${present_dep}")
        should_keep=$(should_keep_dev_dep_for_prod_static_check "${present_dep}")
        if [[ "${should_keep}" != "true" ]]; then
            deps_to_remove+=("${present_dep}")
        fi
    done

    if [ ${#deps_to_remove[@]} -eq 0 ]; then
        edot_log "There should be at least one package to remove to generate composer json derived for test env"
        exit 1
    fi

    echo "${deps_to_remove[*]}"
}

function build_command_to_derive_composer_json_for_prod_static_check() {
    local base_composer_json_full_path="${1:?}"

    # composer json for prod_static_check env is used to run 'composer run-script -- static_check' on prod code
    # so we would like to remove all the dev dependencies that are not used by 'composer run-script -- static_check'
    local dev_deps_to_remove
    dev_deps_to_remove=$(build_list_of_dev_deps_to_remove_for_prod_static_check "${base_composer_json_full_path}")

    echo "composer --no-scripts --no-update --quiet --dev remove ${dev_deps_to_remove}"
}

function should_remove_not_dev_dep_for_test() {
    local dep_name="${1:?}"

    local package_prefixes_to_remove_if_present=("open-telemetry/opentelemetry-auto-")
    local packages_to_remove_if_present=("php-http/guzzle7-adapter")
    packages_to_remove_if_present+=("nyholm/psr7-server")

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

function build_list_of_not_dev_deps_to_remove_for_test() {
    local base_composer_json_full_path="${1:?}"

    mapfile -t present_deps_in_quotes< <(jq '."require" | keys | .[]' "${base_composer_json_full_path}")

    local deps_to_remove=()
    for present_dep_in_quotes in "${present_deps_in_quotes[@]}" ; do
        local present_dep="${present_dep_in_quotes%\"}"
        present_dep="${present_dep#\"}"
        present_deps+=("${present_dep}")
        should_remove=$(should_remove_not_dev_dep_for_test "${present_dep}")
        if [ "${should_remove}" == "true" ] ; then
            deps_to_remove+=("${present_dep}")
        fi
    done

    if [ ${#deps_to_remove[@]} -eq 0 ]; then
        edot_log "There should be at least one package to remove to generate composer json derived for test env"
        exit 1
    fi

    echo "${deps_to_remove[*]}"
}

function build_command_to_derive_for_test() {
    local base_composer_json_full_path="${1:?}"

    # composer json for test env is used in PHPUnit and application code for component tests context
    # so we would like to not have any dependencies that we don't use in tests code and that should be loaded by EDOT package
    # such as open-telemetry/opentelemetry-auto-*, etc.
    # We would like to make sure that those dependencies are loaded by EDOT package and not loaded from tests vendor
    local not_dev_deps_to_remove
    not_dev_deps_to_remove=$(build_list_of_not_dev_deps_to_remove_for_test "${base_composer_json_full_path}")

    echo "composer --no-scripts --no-update --quiet remove ${not_dev_deps_to_remove}"
}

function build_generated_composer_json_full_path() {
    local env_kind="${1:?}"

    local generated_composer_json_file_name
    generated_composer_json_file_name="$(build_generated_composer_json_file_name "${env_kind}")"
    echo "${generated_composer_lock_files_stage_dir}/${generated_composer_json_file_name}"
}

function derive_composer_json_for_env_kind() {
    local env_kind="${1:?}"

    local base_composer_json_full_path
    base_composer_json_full_path="$(build_generated_composer_json_full_path "dev")"

    edot_log "Deriving composer json for ${env_kind} from ${base_composer_json_full_path} ..."

    local derived_composer_json_full_path
    derived_composer_json_full_path="$(build_generated_composer_json_full_path "${env_kind}")"

    local command_to_derive
    case ${env_kind} in
        prod)
            command_to_derive=$(build_command_to_derive_for_prod)
            ;;
        prod_static_check)
            command_to_derive=$(build_command_to_derive_composer_json_for_prod_static_check "${base_composer_json_full_path}")
            ;;
        test)
            command_to_derive=$(build_command_to_derive_for_test "${base_composer_json_full_path}")
            ;;
        *)
            edot_log "There is no way to generate derived composer json for environment kind ${env_kind}"
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

    edot_log "Diff between ${base_composer_json_full_path} and ${derived_composer_json_full_path}"
    local has_compared_the_same="true"
    diff "${base_composer_json_full_path}" "${derived_composer_json_full_path}" || has_compared_the_same="false" 1>&2
    if [ "${has_compared_the_same}" == "true" ]; then
        edot_log "${base_composer_json_full_path} and ${derived_composer_json_full_path} should be different"
        exit 1
    fi
}

function generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local composer_json_full_path
    composer_json_full_path="$(build_generated_composer_json_full_path "${env_kind}")"

    local composer_lock_file_name
    composer_lock_file_name="$(build_generated_composer_lock_file_name "${env_kind}" "${PHP_version_no_dot}")"

    edot_log "Generating ${composer_lock_file_name} from ${composer_json_full_path} ..."

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    edot_log "composer_json_full_path: ${composer_json_full_path} ..."
    edot_log "composer_lock_file_name: ${composer_lock_file_name} ..."

    local docker_args_for_PHP_81=()
    local docker_cmds_for_PHP_81=()
    if [ "${PHP_version_no_dot}" = "81" ]; then
        docker_args_for_PHP_81+=(-v "${repo_temp_copy_dir}/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}:/repo_root/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}:ro")
        docker_args_for_PHP_81+=(-v "${repo_temp_copy_dir}/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}:/from_docker_host/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}:ro")
        docker_args_for_PHP_81+=(-e "COMPOSER_HOME=/repo_root/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}")

        docker_cmds_for_PHP_81+=("&&" mkdir -p "/repo_root/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/")
        docker_cmds_for_PHP_81+=("&&" cp "/from_docker_host/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/"* "/repo_root/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/")
    fi

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${generated_composer_lock_files_stage_dir}:/from_docker_host/generated_composer_lock_files_stage_dir" \
        "${docker_args_for_PHP_81[@]}" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            ${docker_cmds_for_PHP_81[*]} \
            && composer run-script -- generate_lock_use_current_json \
            && cp -f /repo_root/composer.lock /from_docker_host/generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
            && chown -R ${current_user_id}:${current_user_group_id} /from_docker_host/generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
            && chmod -R +r,u+w /from_docker_host/generated_composer_lock_files_stage_dir/${composer_lock_file_name} \
        "
}

function on_script_exit() {
    if [ -n "${repo_temp_copy_dir+x}" ] && [ -d "${repo_temp_copy_dir}" ]; then
        delete_temp_dir "${repo_temp_copy_dir}"
    fi
}

function main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${PWD}")"
    source "${repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    current_user_id="$(id -u)"
    current_user_group_id="$(id -g)"

    trap on_script_exit EXIT

    repo_temp_copy_dir="$(mktemp -d)"
    edot_log "repo_temp_copy_dir: ${repo_temp_copy_dir}"
    
    copy_file "${repo_root_dir}/composer.json" "${repo_temp_copy_dir}/"

    pushd "${repo_temp_copy_dir}" || exit 1
        # composer run-script -- download_and_adapt_packages_to_PHP_81 "${repo_temp_copy_dir}"
        #   expects
        #       - "./composer.json"
        #   creates
        #       - "./${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}/"
        #       - "./${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/config.json"
        php "${this_script_dir}/download_adapt_packages_to_PHP_81_and_gen_config.php"
    popd || exit 1

    generated_composer_lock_files_stage_dir="${repo_temp_copy_dir}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}"
    mkdir -p "${generated_composer_lock_files_stage_dir}"

    local dev_composer_json_full_path
    dev_composer_json_full_path="$(build_generated_composer_json_full_path "dev")"
    copy_file "${repo_root_dir}/composer.json" "${dev_composer_json_full_path}"

    edot_log "ls -al ${repo_temp_copy_dir}"
    ls -al "${repo_temp_copy_dir}" 1>&2
    edot_log "ls -al ${generated_composer_lock_files_stage_dir}"
    ls -al "${generated_composer_lock_files_stage_dir}" 1>&2

    for env_kind in "prod" "prod_static_check" "test"; do
        derive_composer_json_for_env_kind "${env_kind}"
    done

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "dev" "prod" "prod_static_check" "test"; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    pushd "${repo_temp_copy_dir}" || exit 1
        php "${repo_root_dir}/tools/build/verify_generated_composer_lock_files.php"
    popd || exit 1

    mkdir -p "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"
    delete_dir_contents "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"
    cp "${generated_composer_lock_files_stage_dir}/"* "${elastic_otel_php_build_tools_composer_lock_files_dir:?}/"

    # No need for delete_temp_dir "${repo_temp_copy_dir}" - ${repo_temp_copy_dir} is deleted in on_script_exit()
}

main "$@"
