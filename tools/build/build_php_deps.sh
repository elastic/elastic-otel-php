#!/usr/bin/env bash
set -e -o pipefail
#set -x

SKIP_NOTICE=false
KEEP_COMPOSER_LOCK=false

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --skip_notice            Optional. Skip notice file generator."
    echo "  --keep_composer_lock     Optional. Keep composer.lock file."
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
            --keep_composer_lock)
                KEEP_COMPOSER_LOCK=true
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

verify_otel_proto_version() {
    local vendor_dir="${1:?}"

    local otel_proto_version_in_properties_file="${elastic_otel_php_otel_proto_version:?}"

    local gen_otlp_protobuf_version_file_path="${vendor_dir}/open-telemetry/gen-otlp-protobuf/VERSION"
    if [ ! -f "${gen_otlp_protobuf_version_file_path}" ]; then
        echo "File ${gen_otlp_protobuf_version_file_path} does not exist"
        return 1
    fi

    local otel_proto_version_in_gen_otlp_protobuf
    otel_proto_version_in_gen_otlp_protobuf="$(cat "${gen_otlp_protobuf_version_file_path}")"

    if [ "${otel_proto_version_in_properties_file}" != "${otel_proto_version_in_gen_otlp_protobuf}" ]; then
        echo "Versions in elastic-otel-php.properties and ${gen_otlp_protobuf_version_file_path} are different"
        echo "Version in elastic-otel-php.properties: ${otel_proto_version_in_properties_file}"
        echo "Version in ${gen_otlp_protobuf_version_file_path}: ${otel_proto_version_in_gen_otlp_protobuf}"
        return 1
    fi
}

verify_otlp_exporters() {
    local PHP_VERSION="${1:?}"
    local vendor_dir="${2:?}"

    local php_impl_package_name="open-telemetry/exporter-otlp"

    local base_php_impl_vendor_dir="${PWD}/z_local/temp/verify_otlp_exporters/vendor"
    mkdir -p "${base_php_impl_vendor_dir}"
    echo "Content of ${base_php_impl_vendor_dir}"
    ls -al "${base_php_impl_vendor_dir}"

    docker run --rm \
        -v "${base_php_impl_vendor_dir}:/app/vendor" \
        -w /app \
        "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" sh -c "\
        apt-get update && apt-get install -y unzip git \
        && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
        && composer require ${php_impl_package_name}:${elastic_otel_php_native_otlp_exporters_based_on_php_impl_version:?} \
        && composer --no-dev install"

    echo "Content of ${base_php_impl_vendor_dir}"
    ls -al "${base_php_impl_vendor_dir}"

    local base_php_impl_package_dir="${base_php_impl_vendor_dir}/${php_impl_package_name}"
    echo "Content of ${base_php_impl_package_dir}"
    ls -al "${base_php_impl_package_dir}"
    local current_php_impl_package_dir="${vendor_dir}/${php_impl_package_name}"
    echo "Content of ${current_php_impl_package_dir}"
    ls -al "${current_php_impl_package_dir}"

    if ! diff -r "${base_php_impl_package_dir}" "${current_php_impl_package_dir}" ; then
        echo "${base_php_impl_package_dir} and ${current_php_impl_package_dir} have different content"
        echo "1) If the changes require it make sure native implementation is updated"
        echo "2) Set native_otlp_exporters_based_on_php_impl_version in elastic-otel-php.properties to the version of ${php_impl_package_name} in composer.json"
        return 1
    fi
}

verify_vendor_dir() {
    local PHP_VERSION="${1:?}"
    local vendor_dir="${2:?}"

    verify_otel_proto_version "${vendor_dir}"
    verify_otlp_exporters "${PHP_VERSION}" "${vendor_dir}"
}

main() {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"

    repo_root_dir="$( realpath "${this_script_dir}/../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    # Validate required arguments
    # SC2128: Expanding an array without an index only gives the first element.
    # shellcheck disable=SC2128
    if [[ -z "$PHP_VERSIONS" ]]; then
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

        local vendor_dir="${PWD}/prod/php/vendor_${PHP_VERSION}"
        docker run --rm \
            -v "${PWD}:/sources" \
            -v "${vendor_dir}:/sources/vendor" \
            -e "GITHUB_SHA=${GITHUB_SHA}" \
            -w /sources \
            "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" sh -c "\
            apt-get update && apt-get install -y unzip git \
            && git config --global --add safe.directory /sources \
            && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && composer --ignore-platform-req=php --no-dev install \
            ${GEN_NOTICE} \
            && chmod 666 /sources/composer.lock"

        if [ "${KEEP_COMPOSER_LOCK}" = true ]; then
            echo "Keeping composer.lock file"
        else
            rm -f composer.lock
        fi

        verify_vendor_dir "${PHP_VERSION}" "${vendor_dir}"
    done
}

main "$@"
