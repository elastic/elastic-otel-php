#!/usr/bin/env bash
set -e -o pipefail
#set -x

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo
    echo "Example:"
    echo "  $0 --php_versions '81 82 83 84'"
}

# Function to parse arguments
parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --php_versions)
                # SC2206: Quote to prevent word splitting/globbing, or split robustly with mapfile or read -a.
                # shellcheck disable=SC2206
                PHP_versions_no_dot=($2)
                shift
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

main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    parse_args "$@"

    # SC2128: Expanding an array without an index only gives the first element.
    # shellcheck disable=SC2128
    if [[ -z "$PHP_versions_no_dot" ]]; then
        echo "Error: Missing required arguments."
        show_help
        exit 1
    fi

    verify_composer_json_in_sync_with_dev_copy

    for PHP_version_no_dot in "${PHP_versions_no_dot[@]}"; do
        local PHP_docker_image
        PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

        local composer_json_full_path="${elastic_otel_php_build_tools_composer_lock_files_dir:?}/${elastic_otel_php_build_tools_composer_json_for_tests_file_name:?}"

        local composer_lock_file_name
        composer_lock_file_name="$(build_composer_lock_file_name_for_PHP_version "tests" "${PHP_version_no_dot}")"
        local composer_lock_full_path="${elastic_otel_php_build_tools_composer_lock_files_dir:?}/${composer_lock_file_name}"

        local composer_additional_cmd_opts=(--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry)
        if [[ "${PHP_version_no_dot}" = "81" ]]; then
            # We use `--ignore-platform-req=php' and not `config --global platform.php 8.2'
            # because with the latter approach composer still checks the actual PHP version
            # when `composer installed' is executed
            echo 'Forcing composer to ignore actual PHP version'
            composer_additional_cmd_opts+=(--ignore-platform-req=php)
        fi

        docker run --rm \
            -v "${PWD}:/repo_root:ro" \
            -v "${composer_json_full_path}:/composer_to_use.json:ro" \
            -v "${composer_lock_full_path}:/composer_to_use.lock:ro" \
            -w "/" \
            "${PHP_docker_image}" \
            sh -c "\
                mkdir -p /tmp/work_dir && cp -r /repo_root /tmp/work_dir/ && cd /tmp/work_dir/repo_root/ \
                && rm -rf ./vendor/ ./prod/php/vendor_* \
                && cp -f /composer_to_use.json ./composer.json \
                && cp -f /composer_to_use.lock ./composer.lock \
                && apk update && apk add bash git \
                && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
                && composer --check-lock --no-check-all validate \
                && ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND=true composer --no-interaction ${composer_additional_cmd_opts[*]} install \
                && composer run-script -- static_check_and_run_unit_tests \
            "
    done
}

main "$@"
