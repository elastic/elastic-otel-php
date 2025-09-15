#!/usr/bin/env bash
set -e -o pipefail
#set -x

show_help() {
    echo "Usage: $0 --php_versions <versions> --snyk_token <token>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --snyk_token             Required. Snyk API token."
    echo
    echo "Example:"
    echo "  $0 --php_versions '81 82 83 84' --snyk_token 'your_token_here'"
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
        --snyk_token)
            SNYK_TOKEN="$2"
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

    # Parse arguments
    parse_args "$@"

    # Validate required arguments
    # SC2128: Expanding an array without an index only gives the first element.
    # shellcheck disable=SC2128
    if [[ -z "${PHP_versions_no_dot}" ]]; then
        echo "Error: Missing required arguments."
        show_help
        exit 1
    fi

    if [[ -z "${SNYK_TOKEN}" ]]; then
        echo "Error: Missing Styk token required argument."
        show_help
        exit 1
    fi

    for PHP_version_no_dot in "${PHP_versions_no_dot[@]}"; do
        local PHP_version_dot_separated
        PHP_version_dot_separated=$(convert_no_dot_to_dot_separated_version "${PHP_version_no_dot}")

        echo "Scanning PHP dependencies for PHP version ${PHP_version_dot_separated} ..."

        local composer_lock_file_name
        composer_lock_file_name="$(build_composer_lock_file_name_for_PHP_version "prod" "${PHP_version_no_dot}")"
        local composer_lock_full_path="${elastic_otel_php_build_tools_composer_lock_files_dir:?}/${composer_lock_file_name}"
        local composer_json_full_path="${elastic_otel_php_build_tools_composer_lock_files_dir:?}/${elastic_otel_php_build_tools_composer_json_for_prod_file_name:?}"

        if [ ! -f "${composer_lock_full_path}" ]; then
            echo "Error: Composer lock file not found at ${composer_lock_full_path}"
            exit 1
        fi
        if [ ! -f "${composer_json_full_path}" ]; then
            echo "Error: Composer JSON file not found at ${composer_json_full_path}"
            exit 1
        fi



        docker run --rm \
            --env SNYK_TOKEN \
            -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
            -v "${composer_lock_full_path}:/repo_root/composer.lock:ro" \
            -w /repo_root \
            snyk/snyk:php snyk monitor --org="a8dc6395-2bbd-4724-9d9b-8cc417ecdb52" --project-name="elastic-otel-php-PHP${PHP_version_dot_separated}-prod"

        if [ $? -ne 0 ]; then
            echo "Snyk scan failed for PHP version ${PHP_version_no_dot}"
            exit 1
        fi
    done
}

main "$@"
