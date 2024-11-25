#!/usr/bin/env bash
set -xe -o pipefail

show_help() {
    echo "Usage: $0 <PHP version>"
    echo
    echo "Arguments:"
    echo "  <PHP version>   Required. PHP version in the format <MAJOR>.<MINOR>, for example 8.4"
    echo
    echo "Example:"
    echo "  $0 8.4"
}

main() {
    local PHP_VERSION="$1"

    if [[ -z "${PHP_VERSION}" ]]; then
        echo "Error: Missing required argument <PHP version>"
        show_help
        exit 1
    fi

    docker run --rm -v "${PWD}:/app" -w /app "php:${PHP_VERSION}-cli" sh -c "\
        apt-get update && apt-get install -y unzip \
        && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
        && composer --ignore-platform-req=ext-opentelemetry --ignore-platform-req=php install \
        && composer run-script -- static_check"
}

main "$@"
