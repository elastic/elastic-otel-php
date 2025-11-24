#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function exec_composer_outdated() {
    local PHP_version_no_dot="${1:?}"

    local composer_json_full_path="${work_repo_root_dir}/${elastic_otel_php_generated_composer_lock_files_dir_name:?}/${elastic_otel_php_generated_composer_files_base_file_name:?}.json"

    local composer_lock_file_name
    composer_lock_file_name="$(build_generated_composer_lock_file_name "${PHP_version_no_dot}")"
    local composer_lock_full_path="${work_repo_root_dir}/${elastic_otel_php_generated_composer_lock_files_dir_name:?}/${composer_lock_file_name}"

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    local PHP_version_dot_separated
    PHP_version_dot_separated="$(convert_no_dot_to_dot_separated_version "${PHP_version_no_dot}")"

    docker run --rm \
        -v "${src_repo_root_dir}/elastic-otel-php.properties:/repo_root/elastic-otel-php.properties:ro" \
        -v "${src_repo_root_dir}/tools:/repo_root/tools:ro" \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${composer_lock_full_path}:/repo_root/composer.lock:ro" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            apk update && apk add bash \
            && ./tools/install_composer.sh \
            && composer --check-lock --no-check-all validate > /dev/null \
            && echo \"------------------------------------------------------------------------\" \
            && echo \"----------------------------------------\" \
            && echo \"----------------\" \
            && echo \"----\" \
            && echo \"'composer outdated' for PHP ${PHP_version_dot_separated}\" \
            && echo \"----\" \
            && echo \"----------------\" \
            && composer --locked --sort-by-age --ignore-platform-req=ext-mysqli --ignore-platform-req=ext-pgsql --ignore-platform-req=ext-opentelemetry outdated \
            && echo \"----------------\" \
            && echo \"----------------------------------------\" \
            && echo \"------------------------------------------------------------------------\" \
        "
}

function main() {
    work_repo_root_dir="$(realpath "${PWD}")"
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    php "${src_repo_root_dir}/tools/build/verify_generated_composer_lock_files.php"

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        exec_composer_outdated "${PHP_version_no_dot}"
    done
}

main "$@"
