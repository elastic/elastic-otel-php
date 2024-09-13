#!/bin/bash

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '80 81 82 83')."
    echo
    echo "Example:"
    echo "  $0 --php_versions '80 81 82 83'"
}

# Function to parse arguments
parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --php_versions)
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

# Parse arguments
parse_args "$@"

# Validate required arguments
if [[ -z "$PHP_VERSIONS" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

for PHP_VERSION in "${PHP_VERSIONS[@]}"
do
    mkdir -p "prod/php/vendor_${PHP_VERSION}"

    echo "This project depends on following packages for PHP ${PHP_VERSION:0:1}.${PHP_VERSION:1:1}" >>NOTICE

    docker run --rm \
        -v ${PWD}:/sources \
        -v ${PWD}/prod/php/vendor_${PHP_VERSION}:/sources/vendor \
        -e GITHUB_SHA=${GITHUB_SHA} \
        -w /sources \
        php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli sh -c "\
        apt-get update && apt-get install -y unzip git \
        && git config --global --add safe.directory /sources \
        && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
        && composer --ignore-platform-req=ext-opentelemetry --ignore-platform-req=ext-otel_instrumentation --ignore-platform-req=php --no-dev install \
        && php /sources/packaging/notice_generator.php >>/sources/NOTICE \
        && chmod 666 /sources/composer.lock"

    rm -f composer.lock

done
