#!/usr/bin/env bash
set -e -o pipefail
#set -x

generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local composer_lock_filename
    composer_lock_filename="$(build_composer_lock_file_name_for_PHP_version "${env_kind}" "${PHP_version_no_dot}")"

    echo "Generating ${composer_lock_filename} ..."

    local composer_cmd_to_adapt_config_platform_php_req=""
    if [[ "${PHP_version_no_dot}" = "81" ]]; then
        echo 'Forcing composer to assume that PHP version is 8.2'
        composer_cmd_to_adapt_config_platform_php_req="&& composer config --global platform.php 8.2"
    fi

    local composer_json_full_path
    case ${env_kind} in
        dev)
            composer_json_full_path="${original_composer_json_copy_full_path}"
            ;;
        prod)
            ;&
        tests)
            composer_json_full_path="$(build_derived_composer_json_full_path "${env_kind}" "${composer_lock_files_temp_dir}")"
            ;;
        *)
            echo "Unknown environment kind: ${env_kind}"
            exit 1
            ;;
    esac

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    local composer_additional_cmd_opts="--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry"

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${composer_lock_files_temp_dir}:/composer_lock_files_temp_dir" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            ${composer_cmd_to_adapt_config_platform_php_req} \
            && composer --no-scripts --no-install --no-interaction ${composer_additional_cmd_opts} update \
            && cp -f /repo_root/composer.lock /composer_lock_files_temp_dir/${composer_lock_filename} \
            && chown ${current_user_id}:${current_user_group_id} /composer_lock_files_temp_dir/${composer_lock_filename} \
            && chmod +r,u+w /composer_lock_files_temp_dir/${composer_lock_filename} \
        "
}

function on_script_exit() {
    if [[ -d "${composer_lock_files_temp_dir}" ]]; then
        echo "Deleting directory for temporary files: ${composer_lock_files_temp_dir}"
        rm -rf "${composer_lock_files_temp_dir}"
    fi
}

function generate_derived_composer_json_and_verify_diff_to_dev() {
    local env_kind="${1:?}"

    echo "Generating composer json for ${env_kind}..."

    local derived_composer_json_full_path
    derived_composer_json_full_path="$(build_derived_composer_json_full_path "${env_kind}" "${composer_lock_files_temp_dir}")"

    generate_derived_composer_json "${original_composer_json_copy_full_path}" "${env_kind}" "${derived_composer_json_full_path}"

    echo "Diff between ${original_composer_json_copy_full_path} and ${derived_composer_json_full_path}"
    local has_compared_the_same="true"
    diff "${original_composer_json_copy_full_path}" "${derived_composer_json_full_path}" || has_compared_the_same="false"
    if [ "${has_compared_the_same}" = "true" ]; then
        echo "${original_composer_json_copy_full_path} and ${derived_composer_json_full_path} should be different"
        exit 1
    fi
}

main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    current_user_id="$(id -u)"
    current_user_group_id="$(id -g)"

    rm -rf "${elastic_otel_php_build_tools_composer_lock_files_dir:?}"/*

    composer_lock_files_temp_dir="${elastic_otel_php_build_tools_composer_lock_files_dir:?}/temp_PID_$$"
    mkdir -p "${composer_lock_files_temp_dir}"
    trap on_script_exit EXIT

    original_composer_json_copy_full_path="${composer_lock_files_temp_dir}/${elastic_otel_php_build_tools_composer_json_for_dev_file_name:?}"
    echo "Copying ${repo_root_dir}/composer.json to ${original_composer_json_copy_full_path}..."
    cp "${repo_root_dir}/composer.json" "${original_composer_json_copy_full_path}"
    for env_kind in "prod" "tests"; do
        generate_derived_composer_json_and_verify_diff_to_dev "${env_kind}"
    done

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "dev" "prod" "tests"; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    mv --force "${composer_lock_files_temp_dir}"/* "${elastic_otel_php_build_tools_composer_lock_files_dir:?}/"
}

main "$@"
