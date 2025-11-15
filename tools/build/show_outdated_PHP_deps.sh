#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    php "${src_repo_root_dir}/tools/build/verify_generated_composer_lock_files.php"

    local composer_json_file_name
    composer_json_file_name="$(build_composer_json_file_name "${env_kind}")"
    local composer_json_full_path="${work_repo_root_dir}/${composer_json_file_name}"

    local composer_lock_file_name
    composer_lock_file_name="$(build_generated_composer_lock_file_name "${env_kind}" "${PHP_version_no_dot}")"
    local composer_lock_full_path="${work_repo_root_dir}/${composer_json_file_name}"

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    echo "composer_json_full_path: ${composer_json_full_path} ..."
    echo "composer_lock_file_name: ${composer_lock_file_name} ..."

    local PHP_version_dot_separated
    PHP_version_dot_separated="$(convert_no_dot_to_dot_separated_version "${PHP_version_no_dot}")"

    local docker_vendor_mount_opt=()
    local install_vendor_in_docker_cmd=""
    case "${env_kind}" in
        "dev")
            docker_vendor_mount_opt=(-v "${work_repo_root_dir}/vendor/:/repo_root/vendor/:ro")
            ;;
        "prod")
            # TODO: Sergey Kleyman: REMOVE: prod: vendor -> vendor
            docker_vendor_mount_opt=(-v "${work_repo_root_dir}/vendor/:/repo_root/vendor/:ro")
            # TODO: Sergey Kleyman: UNCOMMENT: vendor_prod -> vendor
#            docker_vendor_mount_opt=(-v "${work_repo_root_dir}/vendor_prod/:/repo_root/vendor/:ro")
            ;;
        *)
            echo "Unknown env_kind: ${env_kind}"
            return 1
            ;;
    esac

    docker run --rm \
        -v "${composer_json_full_path}:/repo_root/composer.json:ro" \
        -v "${composer_lock_full_path}:/repo_root/composer_to_use.lock:ro" \
        "${docker_vendor_mount_opt[@]}" \
        -w "/repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
            && cp /repo_root/composer_to_use.lock /repo_root/composer.lock \
            ${install_vendor_in_docker_cmd} \
            && echo \"------------------------------------------------------------------------\" \
            && echo \"----------------------------------------\" \
            && echo \"----------------\" \
            && echo \"----\" \
            && echo \"'composer outdated' for ${composer_json_file_name} and PHP ${PHP_version_dot_separated}\" \
            && echo \"----\" \
            && echo \"----------------\" \
            && composer outdated \
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

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "${elastic_otel_php_deps_env_kinds[@]:?}" ; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    # TODO: Sergey Kleyman: Implement: ./tools/build/show_outdated_PHP_deps.sh
}

main "$@"
