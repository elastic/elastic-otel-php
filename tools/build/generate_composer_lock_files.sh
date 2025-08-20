#!/usr/bin/env bash
set -e -o pipefail
#set -x

temp_dir_on_host="${PWD}/temp_composer_lock_file_PID_$$"
composer_json_for_prod_full_path="${temp_dir_on_host}/composer_prod.json"
current_user_id="$(id -u)"
current_user_group_id="$(id -g)"

generate_composer_lock_for_PHP_version() {
    local php_version_no_dot="${1:?}"
    local dev_or_prod="${2:?}"

    local composer_lock_filename
    composer_lock_filename="$(build_composer_lock_file_name_for_PHP_version "${php_version_no_dot}" "${dev_or_prod}")"

    echo "Generating ${composer_lock_filename} ..."

    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")

    local composer_cmd_to_adapt_config_platform_php_req=""
    if [[ "${php_version_no_dot}" = "81" ]]; then
        echo 'Forcing composer to assume that PHP version is 8.2'
        composer_cmd_to_adapt_config_platform_php_req="&& composer config --global platform.php 8.2"
    fi

    local composer_additional_cmd_opts="--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry"

    local composer_json_full_path="${PWD}/composer.json"
    if [[ "${dev_or_prod}" = "prod" ]]; then
        composer_json_full_path="${composer_json_for_prod_full_path}"
        composer_additional_cmd_opts+=" --no-dev"
    fi

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${temp_dir_on_host}:/temp_dir_on_host" \
        -w "/repo_root" \
        "php:${php_version_dot_separated}-cli-alpine" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            ${composer_cmd_to_adapt_config_platform_php_req} \
            && composer --no-scripts --no-install --no-interaction ${composer_additional_cmd_opts} update \
            && cp -f /repo_root/composer.lock /temp_dir_on_host/${composer_lock_filename} \
            && chown ${current_user_id}:${current_user_group_id} /temp_dir_on_host/${composer_lock_filename} \
            && chmod +r,u+w /temp_dir_on_host/${composer_lock_filename} \
        "
}

function on_script_exit() {
    if [[ -d "${temp_dir_on_host}" ]]; then
        echo "Deleting directory for temporary files: ${temp_dir_on_host}"
        rm -rf "${temp_dir_on_host}"
    fi
}

main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    rm -f composer_lock_* composer_prod.json

    mkdir -p "${temp_dir_on_host}"
    trap on_script_exit EXIT

    echo "Generating composer.json for production..."
    generate_composer_json_for_prod "${composer_json_for_prod_full_path}"
    echo "Diff between ${PWD}/composer.json and ${composer_json_for_prod_full_path}"
    local has_compared_the_same="true"
    diff "${PWD}/composer.json" "${composer_json_for_prod_full_path}" || has_compared_the_same="false"
    if [ "${has_compared_the_same}" = "true" ]; then
        echo "${PWD}/composer.json and ${composer_json_for_prod_full_path} should be different"
        exit 1
    fi

    for php_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for dev_or_prod in "dev" "prod"; do
            generate_composer_lock_for_PHP_version "${php_version_no_dot}" "${dev_or_prod}"
        done
    done

    mv --force "${temp_dir_on_host}"/* "${PWD}/"
}

main "$@"
