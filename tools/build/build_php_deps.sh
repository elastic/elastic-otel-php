#!/usr/bin/env bash
set -e -o pipefail
#set -x

SKIP_NOTICE=false
SKIP_VERIFY=false
current_user_id="$(id -u)"
current_user_group_id="$(id -g)"

show_help() {
    echo "Usage: $0 --php_versions <versions>"
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --skip_notice            Optional. Skip notice file generator. Default: false (i.e., NOTICE file is generated)."
    echo "  --skip_verify            Optional. Skip verify step. Default: false (i.e., verify step is executed)."
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
        --skip_verify)
            SKIP_VERIFY=true
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

validate_composer_json_for_prod() {
    local temp_composer_json_for_prod
    temp_composer_json_for_prod=$(mktemp)
    generate_composer_json_for_prod "${temp_composer_json_for_prod}"
    local has_compared_the_same="true"
    diff "${PWD}/composer_prod.json" "${temp_composer_json_for_prod}" || has_compared_the_same="false"
    if [ "${has_compared_the_same}" = "false" ]; then
        echo "Diff between ${PWD}/composer_prod.json and ${temp_composer_json_for_prod}"
        diff "${PWD}/composer_prod.json" "${temp_composer_json_for_prod}" || true
        echo "It seems composer.json was changed after composer_prod.json was generated - you need to re-run ./tools/build/generate_composer_lock_files.sh"
        return 1
    fi
    rm -f "${temp_composer_json_for_prod}"
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

    docker run --rm \
        -v "${vendor_dir}:/new_vendor:ro" \
        -w / \
        "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" \
        sh -c \
        "\
            mkdir /used_as_base && cd /used_as_base \
            && apt-get update && apt-get install -y unzip git \
            && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && composer require ${php_impl_package_name}:${elastic_otel_php_native_otlp_exporters_based_on_php_impl_version:?} \
            && composer --no-dev install \
            && diff -r /used_as_base/vendor/${php_impl_package_name} /new_vendor/${php_impl_package_name} \
        " ||
        has_compared_the_same="false"

    if [ "${has_compared_the_same}" = "false" ]; then
        echo "${vendor_dir}/${php_impl_package_name} content differs from the base"
        echo "It means that PHP implementation of OTLP exporter (i.e., ${php_impl_package_name}) in composer.json differs from the version (which is ${elastic_otel_php_native_otlp_exporters_based_on_php_impl_version:?}) used as the base for the native implementation"
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
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../..")"
    source "${repo_root_dir}/tools/shared.sh"

    source "${repo_root_dir}/tools/read_properties.sh"
    read_properties "${repo_root_dir}/elastic-otel-php.properties" _PROJECT_PROPERTIES

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
        GEN_NOTICE="\
            && echo 'Generating NOTICE file. This may take some time...' \
            && php /sources/packaging/notice_generator.php >>/sources/NOTICE \
            && chown ${current_user_id}:${current_user_group_id} /sources/NOTICE \
            && chmod +r,u+w /sources/NOTICE \
        "
    fi

    if [ "${SKIP_VERIFY}" != "false" ]; then
        echo "Skipping verify step"
    fi

    validate_composer_json_for_prod

    for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
        if [ "$SKIP_NOTICE" = false ]; then
            echo "This project depends on following packages for PHP ${PHP_VERSION:0:1}.${PHP_VERSION:1:1}" >>NOTICE
        fi

        local composer_lock_filename
        composer_lock_filename="$(build_composer_lock_file_name_for_PHP_version "${PHP_VERSION}" "prod")"
        local composer_lock_file
        composer_lock_file="${PWD}/${composer_lock_filename}"
        INSTALLED_SEMCONV_VERSION=$(jq -r '.packages[] | select(.name == "open-telemetry/sem-conv") | .version' "${composer_lock_file}")

        INSTALLED_MAJOR_MINOR=${INSTALLED_SEMCONV_VERSION%.*}
        EXPECTED_MAJOR_MINOR=${_PROJECT_PROPERTIES_OTEL_SEMCONV_VERSION%.*}

        if [[ "$INSTALLED_MAJOR_MINOR" != "$EXPECTED_MAJOR_MINOR" ]]; then
            echo "PHP side semantic conventions version $INSTALLED_MAJOR_MINOR doesn't match native version $EXPECTED_MAJOR_MINOR"
            exit 1
        fi

        local composer_cmd_to_adapt_config_platform_php_req=""
        if [[ "${PHP_VERSION}" = "81" ]]; then
            echo 'Forcing composer to assume that PHP version is 8.2'
            composer_cmd_to_adapt_config_platform_php_req="&& composer config --global platform.php 8.2"
        fi

        local composer_ignore_platform_req_cmd_opts="--ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry"

        local vendor_dir="${PWD}/prod/php/vendor_${PHP_VERSION}"
        mkdir -p "${vendor_dir}"

        docker run --rm \
            -v "${PWD}:/sources" \
            -v "${PWD}/composer_prod.json:/sources/composer.json:ro" \
            -v "${composer_lock_file}:/sources/composer.lock:ro" \
            -v "${vendor_dir}:/sources/vendor" \
            -e "GITHUB_SHA=${GITHUB_SHA}" \
            -w /sources \
            "php:${PHP_VERSION:0:1}.${PHP_VERSION:1:1}-cli" \
            sh -c "\
                apt-get update && apt-get install -y unzip git \
                && git config --global --add safe.directory /sources \
                && curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
                ${composer_cmd_to_adapt_config_platform_php_req} \
                && (composer --check-lock --no-check-all validate \
                    || (echo It seems composer.json was changed after composer lock files were generated - you need to re-run ./tools/build/generate_composer_lock_files.sh && false)) \
                && ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND=true composer --no-dev --no-interaction ${composer_ignore_platform_req_cmd_opts} install \
                && chown -R ${current_user_id}:${current_user_group_id} /sources/vendor \
                && chmod -R +r,u+w /sources/vendor \
                ${GEN_NOTICE} \
            "

        if [ "${SKIP_VERIFY}" = "false" ]; then
            verify_vendor_dir "${PHP_VERSION}" "${vendor_dir}"
        fi
    done
}

main "$@"
