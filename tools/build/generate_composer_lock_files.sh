#!/usr/bin/env bash
set -e -o pipefail
#set -x

composer_lock_temp_dir="${PWD}/temp_composer_lock_file_PID_$$"

update_composer_lock_for_PHP_version() {
    local php_version_no_dot="${1:?}"

    local php_version_dot_separated
    php_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${php_version_no_dot}")

    echo "Generating composer.lock for PHP ${php_version_dot_separated} ..."

    local composer_cmd_to_adapt_config_platform_php_req=""
    if [[ "${php_version_dot_separated}" = "8.1" ]]; then
        composer_cmd_to_adapt_config_platform_php_req="&& echo 'Forcing composer to assume that PHP version is 8.2' && composer config platform.php 8.2"
    fi

    local composer_lock_filename
    composer_lock_filename="$(build_composer_lock_file_name_for_PHP_version "${php_version_no_dot}")"

    docker run --rm \
        -v "${PWD}/composer.json:/original_composer.json:ro" \
        -v "${composer_lock_temp_dir}:/composer_lock_temp_dir" \
        -w /repo_root \
        "php:${php_version_dot_separated}-cli" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && cp /original_composer.json /repo_root/composer.json \
            ${composer_cmd_to_adapt_config_platform_php_req} \
            && composer --no-install --no-interaction --no-scripts update \
            && cp -f composer.lock /composer_lock_temp_dir/${composer_lock_filename} \
        "
}

function on_script_exit() {
    if [[ -d "${composer_lock_temp_dir}" ]]; then
        echo "Deleting directory for temporary files: ${composer_lock_temp_dir}"
        rm -rf "${composer_lock_temp_dir}"
    fi
}

main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    mkdir -p "${composer_lock_temp_dir}"
    trap on_script_exit EXIT

    for php_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        update_composer_lock_for_PHP_version "${php_version_no_dot}"
    done

    mv --force "${composer_lock_temp_dir}"/* "${PWD}/"
}

main "$@"
