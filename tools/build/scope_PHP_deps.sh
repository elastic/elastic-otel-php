#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

# See https://hub.docker.com/r/humbugphp/php-scoper/tags
# TODO: Sergey Kleyman: UNCOMMENT
#php_scoper_container_image_version_to_use="0.18.18"
#PHP_version_to_use="8.4"
#scope_namespace="ScopedByElasticOTel"

#current_user_id="$(id -u)"
#current_user_group_id="$(id -g)"

input_dir=""
output_dir=""

log() {
    echo "${this_script_name}:" "${@}"
}

show_help() {
    local env_kinds_as_string=""
    for env_kind in "${elastic_otel_php_deps_env_kinds[@]:?}" ; do
        if [[ -n "${env_kind}" ]]; then
            env_kinds_as_string+=", "
        fi
        env_kinds_as_string+="${env_kind}"
    done

    echo "Usage: $0 --env_kind <PHP deps env kind>"
    echo
    echo "Arguments:"
    echo "  --input_dir              Required. Input directory."
    echo "  --output_dir             Required. Output directory."
    echo
    echo "Example:"
    echo "  $0 --input_dir /my_project/vendor --output_dir /my_project/build/vendor_scoped"
}

# Function to parse arguments
parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case "${1}" in
        --input_dir)
            input_dir="${2}"
            shift
            shift
            ;;
        --output_dir)
            output_dir="${2}"
            shift
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
    done

    if [ -z "${input_dir}" ]; then
        echo "Error: Required argument <input_dir> is missing."
        show_help
        exit 1
    fi

    if [ -z "${output_dir}" ]; then
        echo "Error: Required argument <output_dir> is missing."
        show_help
        exit 1
    fi
}

function on_script_exit() {
    local exit_code=$?

    if [ -n "${temp_stage_dir}" ] && [ -d "${temp_stage_dir}" ]; then
        delete_temp_dir "${temp_stage_dir}"
    fi

    exit ${exit_code}
}

main() {
    this_script_full_path="${BASH_SOURCE[0]}"
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    this_script_name="$(basename -- "${this_script_full_path}")"
    this_script_name="${this_script_name%.*}"
    src_repo_root_dir="$(realpath "${this_script_dir}/../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    #########################################
    # TODO: Sergey Kleyman: BEGIN: REMOVE:
    ###################
    if [[ "${input_dir}" != "${output_dir}" ]]; then
        if [ -d "${output_dir}" ]; then
            delete_dir_contents "${output_dir}"
        else
            mkdir -p "${output_dir}"
        fi
        copy_dir_contents "${input_dir}" "${output_dir}"
    fi
    ###################
    # END: REMOVE
    #########################################

# TODO: Sergey Kleyman: UNCOMMENT

#    temp_stage_dir=""
#
#    trap on_script_exit EXIT
#
#    temp_stage_dir="$(mktemp -d)"
#    log "temp_stage_dir: ${temp_stage_dir}"
#
#    local PHP_version_to_use_no_dot
#    PHP_version_to_use_no_dot=$(convert_dot_separated_to_no_dot_version "${PHP_version_to_use}")
#    local PHP_docker_image
#    PHP_docker_image=$(build_light_PHP_docker_image_name_for_version_no_dot "${PHP_version_to_use_no_dot}")
#
#    log "Pulling docker image: ${PHP_docker_image} ..."
#    docker pull "${PHP_docker_image}"
#
#    local post_process_command=""
#    case ${env_kind} in
#        prod)
#            post_process_command="&& composer dump-autoload --no-dev --optimize --classmap-authoritative"
#            ;;
#        dev)
#            post_process_command="&& composer dump-autoload --dev"
#            ;;
#        *)
#            echo "Unknown environment kind ${env_kind}"
#            exit 1
#            ;;
#    esac
#
#    # -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
#    local php_scoper_verbosity_opt=""
#    docker run --rm \
#        -v "${PWD}/:/docker_host/read_only_repo_root/:ro" \
#        -v "${temp_stage_dir}/:/docker_host/temp_stage_dir/" \
#        -w / \
#        "${PHP_docker_image}" \
#        sh -c \
#        "\
#            curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin \
#            && echo 'memory_limit=256M' > /usr/local/etc/php/conf.d/custom_config_for_php_scoper.ini \
#            && echo -n 'memory_limit: ' && php -r \"echo ini_get('memory_limit') . PHP_EOL;\" \
#            && mkdir -p /tmp/stage/scoper && cd /tmp/stage/scoper \
#            && cp /docker_host/read_only_repo_root/tools/build/php-scoper.inc.php ./scoper.inc.php \
#            && composer require humbug/php-scoper:${php_scoper_container_image_version_to_use} \
#            && ./vendor/bin/php-scoper \
#                add-prefix \"--prefix=${scope_namespace}\" \
#                --output-dir=/tmp/stage/scoper/output \
#                --no-ansi \
#                --config /docker_host/read_only_repo_root/tools/build/php-scoper.inc.php \
#                --no-interaction \
#                ${php_scoper_verbosity_opt} \
#                /docker_host/read_only_repo_root/vendor \
#            && echo 'ls -l ./vendor' && ls -l ./vendor \
#            && mkdir -p /tmp/stage/dump-autoload && mv /tmp/stage/scoper/output /tmp/stage/dump-autoload/vendor \
#            && cp /docker_host/read_only_repo_root/composer.json /tmp/stage/dump-autoload/ \
#            && cp /docker_host/read_only_repo_root/composer.lock /tmp/stage/dump-autoload/ \
#            && cd /tmp/stage/dump-autoload \
#            ${post_process_command} \
#            && cp -r /tmp/stage/dump-autoload/vendor/. /docker_host/temp_stage_dir/ \
#            && chown -R ${current_user_id}:${current_user_group_id} /docker_host/temp_stage_dir/ \
#            && chmod -R +r,u+w /docker_host/temp_stage_dir/ \
#        "
#    ls -l "${temp_stage_dir}"
#
#    delete_dir_contents "${PWD}/vendor"
#
#    log "Moving contents of ${temp_stage_dir} to ${PWD}/vendor ..."
#    mv "${temp_stage_dir}/"* "${PWD}/vendor/"
}

main "$@"
