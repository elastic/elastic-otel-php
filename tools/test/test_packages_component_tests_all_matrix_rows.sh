#!/usr/bin/env bash
set -xe -o pipefail

show_help() {
    echo "Usage: $0 --matrix_row <matrix row> --filter <tests filter in PHPUnit format>"
    echo
    echo "Arguments:"
    echo "  --filter        Optional. Allows running subset of tests using PHPUnit's --filter"
    echo
    echo "Example:"
    echo "  $0 --filter 'MyTestClass::myTestMethod'"
}

# Function to parse arguments
parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --filter)
                ELASTIC_OTEL_PHP_TESTS_FILTER="$2"
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

    local composerOptions=(run_component_tests_configured_custom_config)
    # SC2128: Expanding an array without an index only gives the first element.
    # shellcheck disable=SC2128
    if [ -n "${ELASTIC_OTEL_PHP_TESTS_FILTER}" ]; then
        composerOptions=("${composerOptions[@]}" --filter "${ELASTIC_OTEL_PHP_TESTS_FILTER}")
    fi

    for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
        docker run --rm -v "${PWD}:/app" -w /app "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" sh -c "\
            cp -r ./prod/php/ ./prod_php_backup/ \
            && apt-get update && apt-get install -y unzip \
            && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && composer --ignore-platform-req=ext-opentelemetry --ignore-platform-req=php install \
            && composer run-script -- ${composerOptions[*]} \
            && rm -rf ./vendor composer.lock ./prod/php \
            && mv ./prod_php_backup ./prod/php \
            "
    done
}

main "$@"
