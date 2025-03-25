#!/bin/bash

SKIP_NOTICE=false
KEEP_COMPOSER=false

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --skip_notice            Optional. Skip notice file generator."
    echo "  --keep_composer          Optional. Keep composer.lock file."
    echo
    echo "Example:"
    echo "  $0 --php_versions '81 82 83 84' --skip_notice"
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
            --skip_notice)
                SKIP_NOTICE=true
                ;;
            --keep_composer)
                KEEP_COMPOSER=true
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
if [[ -z "${PHP_VERSIONS[*]}" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

GEN_NOTICE=""
if [ "$SKIP_NOTICE" = true ]; then
    echo "Skipping notice file generation..."
else
    GEN_NOTICE="&& echo 'Generating NOTICE file. This may take some time...' && php /sources/packaging/notice_generator.php >>/sources/NOTICE"
fi


for PHP_VERSION in "${PHP_VERSIONS[@]}"
do
    mkdir -p "prod/php/vendor_${PHP_VERSION}"

    if [ "$SKIP_NOTICE" = false ]; then
        echo "This project depends on following packages for PHP ${PHP_VERSION:0:1}.${PHP_VERSION:1:1}" >>NOTICE
    fi

    docker run --rm \
        -v "${PWD}:/sources" \
        -v "${PWD}/prod/php/vendor_${PHP_VERSION}:/sources/vendor" \
        -e "GITHUB_SHA=${GITHUB_SHA}" \
        -w /sources \
        "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" sh -c "\
        apt-get update && apt-get install -y unzip git \
        && git config --global --add safe.directory /sources \
        && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
        && composer --ignore-platform-req=php --no-dev install \
        ${GEN_NOTICE} \
        && chmod 666 /sources/composer.lock"

    if [ "$KEEP_COMPOSER" = true ]; then
        echo "Keeping composer.lock file"
    else
        rm -f composer.lock
    fi

done
