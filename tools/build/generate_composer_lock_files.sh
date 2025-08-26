#!/usr/bin/env bash
set -e -o pipefail
#set -x

build_command_to_derive_composer_json_for_prod() {
    # Make some inconsequential change to composer.json just to make the one for dev different from the one for production.
    # So that the hash codes are different and ComposerAutoloaderInit<composer.json hash code> classes defined in vendor/composer/autoload_real.php
    # in the installed package and component tests vendor directories have different names.
    # Note that even though it is `require_once __DIR__ . '/composer/autoload_real.php'` in vendor/autoload.php
    # it does not prevent `Cannot redeclare class` error because those two autoload_real.php files are located in different directories
    # require_once does not help.

    echo "composer --no-scripts --no-update --dev --quiet remove ext-mysqli"
}

should_remove_not_dev_dep_from_composer_json_for_tests() {
    dep_name="${1:?}"

    local package_prefixes_to_remove_if_present=("open-telemetry/opentelemetry-auto-")
    local packages_to_remove_if_present=("php-http/guzzle7-adapter")
    packages_to_remove_if_present+=("nyholm/psr7-server")
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

build_list_of_not_dev_deps_to_remove_from_composer_json_for_tests() {
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

build_command_to_derive_composer_json_for_tests() {
    # composer json for tests is used in PHPUnit and application code for component tests context
    # so we would like to not have any dependencies that we don't use in tests code and that should be loaded by EDOT package
    # such as open-telemetry/opentelemetry-auto-*, etc.
    # We would like to make sure that those dependencies are loaded by EDOT package and not loaded from tests vendor

    local not_dev_deps_to_remove
    not_dev_deps_to_remove=$(build_list_of_not_dev_deps_to_remove_from_composer_json_for_tests)

    echo "composer --no-scripts --no-update --quiet remove ${not_dev_deps_to_remove}"
}

function build_derived_composer_json_full_path() {
    local env_kind="${1:?}"

    local derived_composer_json_file_name
    case ${env_kind} in
        prod)
            derived_composer_json_file_name="${elastic_otel_php_build_tools_composer_json_for_prod_file_name:?}"
            ;;
        tests)
            derived_composer_json_file_name="${elastic_otel_php_build_tools_composer_json_for_tests_file_name:?}"
            ;;
        *)
            echo "There is no way to generate derived composer json for environment kind ${env_kind}"
            exit 1
            ;;
    esac

    echo "${composer_lock_files_temp_dir}/${derived_composer_json_file_name}"
}

function derive_composer_json_for_env_kind() {
    local env_kind="${1:?}"

    echo "Deriving composer json for ${env_kind}..."

    local derived_composer_json_full_path
    derived_composer_json_full_path="$(build_derived_composer_json_full_path "${env_kind}")"

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

    echo "Diff between ${original_composer_json_copy_full_path} and ${derived_composer_json_full_path}"
    local has_compared_the_same="true"
    diff "${original_composer_json_copy_full_path}" "${derived_composer_json_full_path}" || has_compared_the_same="false"
    if [ "${has_compared_the_same}" = "true" ]; then
        echo "${original_composer_json_copy_full_path} and ${derived_composer_json_full_path} should be different"
        exit 1
    fi
}

generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local composer_lock_file_name
    composer_lock_file_name="$(build_composer_lock_file_name_for_PHP_version "${env_kind}" "${PHP_version_no_dot}")"

    echo "Generating ${composer_lock_file_name} ..."

    local composer_additional_cmd_opts=(--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry)
    if [[ "${PHP_version_no_dot}" = "81" ]]; then
        # We use `--ignore-platform-req=php' and not `config --global platform.php 8.2'
        # because with the latter approach composer still checks the actual PHP version
        # when `composer installed' is executed
        local current_PHP_version
        current_PHP_version=$(php -r "echo PHP_VERSION;")
        echo "Forcing composer to ignore actual PHP version (which is ${current_PHP_version})"
        composer_additional_cmd_opts+=(--ignore-platform-req=php)
    fi

    local composer_json_full_path
    case ${env_kind} in
        dev)
            composer_json_full_path="${original_composer_json_copy_full_path}"
            ;;
        prod)
            ;&
        tests)
            composer_json_full_path="$(build_derived_composer_json_full_path "${env_kind}")"
            ;;
        *)
            echo "Unknown environment kind: ${env_kind}"
            exit 1
            ;;
    esac

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    echo "composer_json_full_path: ${composer_json_full_path} ..."
    echo "composer_lock_file_name: ${composer_lock_file_name} ..."

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${composer_lock_files_temp_dir}:/composer_lock_files_temp_dir" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && composer --no-scripts --no-install --no-interaction ${composer_additional_cmd_opts[*]} update \
            && cp -f /repo_root/composer.lock /composer_lock_files_temp_dir/${composer_lock_file_name} \
            && chown ${current_user_id}:${current_user_group_id} /composer_lock_files_temp_dir/${composer_lock_file_name} \
            && chmod +r,u+w /composer_lock_files_temp_dir/${composer_lock_file_name} \
        "
}

function on_script_exit() {
    if [[ -d "${composer_lock_files_temp_dir}" ]]; then
        echo "Deleting directory for temporary files: ${composer_lock_files_temp_dir}"
        rm -rf "${composer_lock_files_temp_dir}"
    fi
}

main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    current_user_id="$(id -u)"
    current_user_group_id="$(id -g)"

    composer_lock_files_temp_dir="$(mktemp -d)"

    trap on_script_exit EXIT

    original_composer_json_copy_full_path="${composer_lock_files_temp_dir}/${elastic_otel_php_build_tools_composer_json_for_dev_file_name:?}"
    echo "Copying ${repo_root_dir}/composer.json to ${original_composer_json_copy_full_path}..."
    cp "${repo_root_dir}/composer.json" "${original_composer_json_copy_full_path}"
    for env_kind in "prod" "tests"; do
        derive_composer_json_for_env_kind "${env_kind}"
    done

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "dev" "prod" "tests"; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    echo "Deleting content of ${elastic_otel_php_build_tools_composer_lock_files_dir:?}/ ..."
    rm -rf "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"/*

    mv --force "${composer_lock_files_temp_dir}"/* "${elastic_otel_php_build_tools_composer_lock_files_dir:?}/"
}

main "$@"
