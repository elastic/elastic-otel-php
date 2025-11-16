#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function show_help() {
    echo "Usage: $0 [optional arguments]"
    echo
    echo "Options:"
    echo "  --keep_temp_files       Optional. Keep temporary files. Default: false (i.e., delete temporary files on both success and failure)."
    echo
    echo "Example:"
    echo "  $0 --keep_temp_files"
}

# Function to parse arguments
function parse_args() {
    export ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES="false"

    while [[ "$#" -gt 0 ]]; do
        case $1 in
        --keep_temp_files)
            export ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES="true"
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

function copy_all_env_kinds_composer_json_files() {
    local dst_dir="${1:?}"

    for env_kind in "${elastic_otel_php_deps_env_kinds[@]:?}" ; do
        local composer_json_for_env_kind_file_name
        composer_json_for_env_kind_file_name="$(build_composer_json_file_name "${env_kind}")"
        copy_file "${src_repo_root_dir}/${composer_json_for_env_kind_file_name}" "${dst_dir}/"
    done
}

function generate_composer_lock_for_PHP_version() {
    local env_kind="${1:?}"
    local PHP_version_no_dot="${2:?}"

    local composer_json_file_name
    composer_json_file_name="$(build_composer_json_file_name "${env_kind}")"
    local composer_json_full_path="${stage_generated_composer_lock_files_dir}/${composer_json_file_name}"

    local composer_lock_file_name
    composer_lock_file_name="$(build_generated_composer_lock_file_name "${env_kind}" "${PHP_version_no_dot}")"

    echo "Generating ${composer_lock_file_name} from ${composer_json_full_path} ..."

    local PHP_docker_image
    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_no_dot}")

    local docker_run_additional_args=()
    if [ "${PHP_version_no_dot}" = "81" ]; then
        docker_run_additional_args+=(-v "${repo_temp_copy_dir}/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}:/docker_host_repo_root/${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}:ro")
        local composer_home_config_json_rel_path="${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/config.json"
        docker_run_additional_args+=(-v "${repo_temp_copy_dir}/${composer_home_config_json_rel_path}:/docker_host_repo_root/${composer_home_config_json_rel_path}:ro")
        docker_run_additional_args+=(-e "COMPOSER_HOME=/docker_host_repo_root/${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}")
    fi

    docker run --rm \
        -v "${composer_json_full_path}:/docker_host_repo_root/composer.json:ro" \
        -v "${src_repo_root_dir}/elastic-otel-php.properties:/docker_host_repo_root/elastic-otel-php.properties:ro" \
        -v "${src_repo_root_dir}/tools:/docker_host_repo_root/tools:ro" \
        -v "${stage_generated_composer_lock_files_dir}:/docker_host_dst/stage_generated_composer_lock_files_dir" \
        "${docker_run_additional_args[@]}" \
        -w "/docker_host_repo_root" \
        "${PHP_docker_image}" \
        sh -c "\
            apk update && apk add bash \
            && ./tools/install_composer.sh \
            && composer run-script -- generate_lock_use_current_json \
            && cp -f /docker_host_repo_root/composer.lock /docker_host_dst/stage_generated_composer_lock_files_dir/${composer_lock_file_name} \
            && chown ${current_user_id}:${current_user_group_id} /docker_host_dst/stage_generated_composer_lock_files_dir/${composer_lock_file_name} \
            && chmod +r,u+w /docker_host_dst/stage_generated_composer_lock_files_dir/${composer_lock_file_name} \
        "
}

function on_script_exit() {
    local exit_code=$?

    if [ -n "${repo_temp_copy_dir+x}" ] && [ -d "${repo_temp_copy_dir}" ]; then
        delete_temp_dir "${repo_temp_copy_dir}"
    fi

    exit ${exit_code}
}

function main() {
    work_repo_root_dir="$(realpath "${PWD}")"
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    current_user_id="$(id -u)"
    current_user_group_id="$(id -g)"

    trap on_script_exit EXIT

    repo_temp_copy_dir="$(mktemp -d)"

    copy_all_env_kinds_composer_json_files "${repo_temp_copy_dir}"

    pushd "${repo_temp_copy_dir}" || exit 1
        # composer run-script -- download_and_adapt_packages_to_PHP_81 "${repo_temp_copy_dir}"
        #   expects
        #       - "./composer_prod.json"
        #   creates
        #       - "./${elastic_otel_php_packages_adapted_to_PHP_81_rel_path:?}/"
        #       - "./${elastic_otel_php_composer_home_for_packages_adapted_to_PHP_81_rel_path:?}/config.json"
        php "${src_repo_root_dir}/tools/build/download_adapt_packages_to_PHP_81_and_gen_config.php"
    popd || exit 1

    stage_generated_composer_lock_files_dir="${repo_temp_copy_dir}/${elastic_otel_php_generated_composer_lock_files_dir_name:?}"
    mkdir -p "${stage_generated_composer_lock_files_dir}"

    copy_all_env_kinds_composer_json_files "${stage_generated_composer_lock_files_dir}"

    for PHP_version_no_dot in "${elastic_otel_php_supported_php_versions[@]:?}" ; do
        for env_kind in "${elastic_otel_php_deps_env_kinds[@]:?}" ; do
            generate_composer_lock_for_PHP_version "${env_kind}" "${PHP_version_no_dot}"
        done
    done

    pushd "${repo_temp_copy_dir}" || exit 1
        php "${src_repo_root_dir}/tools/build/verify_generated_composer_lock_files.php"
    popd || exit 1

    local dst_generated_composer_lock_files_dir="${work_repo_root_dir}/${elastic_otel_php_generated_composer_lock_files_dir_name:?}"
    mkdir -p "${dst_generated_composer_lock_files_dir}"
    delete_dir_contents "${dst_generated_composer_lock_files_dir}"
    cp "${stage_generated_composer_lock_files_dir}/"* "${dst_generated_composer_lock_files_dir}/"
    # No need for delete_temp_dir "${repo_temp_copy_dir}" - ${repo_temp_copy_dir} is deleted in on_script_exit()
}

main "$@"
