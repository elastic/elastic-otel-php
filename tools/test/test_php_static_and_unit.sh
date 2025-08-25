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
                PHP_VERSIONS=($2)
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
    parse_args "$@"

    # SC2128: Expanding an array without an index only gives the first element.
    # shellcheck disable=SC2128
    if [[ -z "$PHP_VERSIONS" ]]; then
        echo "Error: Missing required arguments."
        show_help
        exit 1
    fi

    for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
        docker run --rm \
            -v "${PWD}:/repo_root:ro" \
            -w "/" \
            "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" \
            sh -c "\
                mkdir -p /tmp/work_dir && cp -r /repo_root /tmp/work_dir/ && cd /tmp/work_dir/repo_root/ \
                && apt-get update && apt-get install -y unzip \
                && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
                && composer run-script -- install-using-generated-lock-tests \
                && composer run-script -- static_check_and_run_unit_tests \
            "
    done
}

main "$@"
