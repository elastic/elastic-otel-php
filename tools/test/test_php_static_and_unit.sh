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

        docker run --rm \
            -v "${PWD}:/repo_root:ro" \
            -w "/" \
            "${PHP_docker_image}" \
            sh -c "\
                mkdir -p /tmp/work_dir && cp -r /repo_root /tmp/work_dir/ && cd /tmp/work_dir/repo_root/ \
                && rm -rf ./vendor/ ./prod/php/vendor_* \
                && apk update && apk add bash git \
                && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
                && composer run-script -- install_tests_select_generated_json_lock \
                && composer run-script -- static_check_and_run_unit_tests \
            "
    done
}

main "$@"
