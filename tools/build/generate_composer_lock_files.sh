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
    if [[ "${php_version_no_dot}" = "81" ]]; then
        echo 'Forcing composer to assume that PHP version is 8.2'
        composer_cmd_to_adapt_config_platform_php_req="&& composer config --global platform.php 8.2"
    fi

    local composer_lock_filename
    composer_lock_filename="$(build_composer_lock_file_name_for_PHP_version "${php_version_no_dot}")"

    local composer_ignore_platform_req_cmd_opts="--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry"

    docker run --rm \
        -v "${PWD}/composer.json:/repo_root/composer.json:ro" \
        -v "${composer_lock_temp_dir}:/composer_lock_temp_dir" \
        -w /repo_root \
        "php:${php_version_dot_separated}-cli" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            ${composer_cmd_to_adapt_config_platform_php_req} \
            && ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND=true composer --no-install --no-interaction ${composer_ignore_platform_req_cmd_opts} update \
            && cp -f /repo_root/composer.lock /composer_lock_temp_dir/${composer_lock_filename} \
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
