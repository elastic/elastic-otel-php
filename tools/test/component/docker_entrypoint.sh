#!/usr/bin/env bash
set -xe -o pipefail

this_script_full_path="${BASH_SOURCE[0]}"
this_script_dir="$( dirname "${this_script_full_path}" )"
this_script_dir="$( realpath "${this_script_dir}" )"
this_script_name="$(basename -- "${this_script_full_path}")"
this_script_name="${this_script_name%.*}"

repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
source "${repo_root_dir}/tools/shared.sh"

install_package_file() {
    local package_file_full_path=${1:?}

    local package_file_name_with_ext
    package_file_name_with_ext=$(basename "${package_file_full_path}")
    local package_file_extension
    package_file_extension="${package_file_name_with_ext##*.}"

    case "${package_file_extension}" in
        apk)
            apk add --allow-untrusted "${package_file_full_path}"
            ;;
        deb)
            dpkg -i "${package_file_full_path}"
            ;;
        rpm)
            rpm -ivh "${package_file_full_path}"
            ;;
        *)
            echo "Unknown package file extension: ${package_file_extension}, package_file_full_path: ${package_file_full_path}"
            exit 1
            ;;
    esac
}

function install_elastic_otel_package () {
    echo "::group::Installing package with Elastic OTel for PHP distro"

    # Until we add testing for ARM architecture is hardcoded as x86_64
    local architecture="x86_64"

    local package_file_full_path
    package_file_full_path=$(select_elastic_otel_package_file /elastic_otel_php_tests/packages "${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}" "${architecture}")

    install_package_file "${package_file_full_path}"

    echo "::endgroup::"
}

function start_syslog () {
    if which syslogd; then
        syslogd
    else
        if which rsyslogd; then
            rsyslogd
        else
            echo 'false'
            return
        fi
    fi

    echo 'false'
}

function on_script_exit () {
    exitCode=$?

    echo "::group::Copying syslog files"
    local var_log_dst_dir=/elastic_otel_php_tests/logs/var_log
    mkdir -p "${var_log_dst_dir}"
    cp -r /var/log/syslog* "${var_log_dst_dir}" || true
    cp -r /var/log/messages* "${var_log_dst_dir}" || true
    echo "::endgroup::"

    local number_of_lines=100
    echo "::group::Last ${number_of_lines} lines of /elastic_otel_php_tests/logs/composer_run_component_tests.log"
    tail -n ${number_of_lines} /elastic_otel_php_tests/logs/composer_run_component_tests.log || true
    echo "::endgroup::"

    exit ${exitCode}
}

main() {
    echo "pwd"
    pwd

    echo "ls -l"
    ls -l

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

# TODO: Sergey Kleyman: UNCOMMENT
#    local start_syslog_started
#    start_syslog_started=$(start_syslog)
#    if [ "${start_syslog_started}" != "true" ]; then
#        # By default tests log level escalation mechanism uses log_level_syslog production option
#        # If there is not syslog running then let's use log_level_stderr
#        export ELASTIC_OTEL_PHP_TESTS_ESCALATED_RERUNS_PROD_CODE_LOG_LEVEL_OPTION_NAME=log_level_stderr
#    fi
    # It seems productions code writes to syslog so let's use stderr for now
    export ELASTIC_OTEL_PHP_TESTS_ESCALATED_RERUNS_PROD_CODE_LOG_LEVEL_OPTION_NAME=log_level_stderr

    trap on_script_exit EXIT

    # Disable agent for auxiliary PHP processes to reduce noise in logs
    export ELASTIC_OTEL_ENABLED=false
    export OTEL_PHP_DISABLED_INSTRUMENTATIONS=all
    export OTEL_PHP_AUTOLOAD_ENABLED=false

    install_elastic_otel_package

    echo "::group::Installing PHP dependencies using composer"
    if [ -f /repo_root/composer.lock ]; then
        rm -f /repo_root/composer.lock
    fi

    # Remove "open-telemetry/opentelemetry-auto-.*": lines from composer.json
    cp /repo_root/composer.json /repo_root/composer.json.original
    grep -v -E '"open-telemetry/opentelemetry-auto-.*":' /repo_root/composer.json.original > /repo_root/composer.json

    cat /repo_root/composer.json

    composer install
    echo "::endgroup::"

    /repo_root/tools/test/component/test_installed_package_one_matrix_row.sh
}

main "$@"
