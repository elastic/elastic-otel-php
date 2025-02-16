#!/usr/bin/env bash
set -e -o pipefail
#set -x

function install_package_file() {
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
    local current_workflow_group_name="Installing package with Elastic OTel for PHP distro"
    start_github_workflow_log_group "${current_workflow_group_name}"

    # Until we add testing for ARM architecture is hardcoded as x86_64
    local architecture="x86_64"

    local package_file_full_path
    package_file_full_path=$(select_elastic_otel_package_file /elastic_otel_php_tests/packages "${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}" "${architecture}")

    install_package_file "${package_file_full_path}"

    end_github_workflow_log_group "${current_workflow_group_name}"
}

function start_syslog () {
    if which syslogd; then
        syslogd
    else
        if which rsyslogd; then
            rsyslogd
        fi
    fi

    # SC2009: Consider using pgrep instead of grepping ps output.
    # shellcheck disable=SC2009
    if ps -ef | grep syslogd | grep -v grep ; then
        echo 'true'
    else
        echo 'false'
    fi
}

function start_syslog_and_set_related_config () {
    local start_syslog_started
    start_syslog_started=$(start_syslog)
    if [ "${start_syslog_started}" != "true" ]; then
        # By default tests log level escalation mechanism uses log_level_syslog production option
        # If there is not syslog running then let's use log_level_stderr
        export ELASTIC_OTEL_PHP_TESTS_ESCALATED_RERUNS_PROD_CODE_LOG_LEVEL_OPTION_NAME=log_level_stderr
    fi
}

function extract_log_related_to_failure () {
    local current_workflow_group_name="Extracting part of log related to failure"
    start_github_workflow_log_group "${current_workflow_group_name}"
    local composer_run_component_tests_log_file=/elastic_otel_php_tests/logs/composer_-_run_component_tests.log

    if [ ! -f "${composer_run_component_tests_log_file}" ]; then
        end_github_workflow_log_group "${current_workflow_group_name}"
        return
    fi

    local last_starting_test_case_line
    last_starting_test_case_line="$(grep -n -E "^Starting test case: " "${composer_run_component_tests_log_file}" | tail -1)"
    if [[ -z "${last_starting_test_case_line}" ]]; then # -z is true if string is empty
        end_github_workflow_log_group "${current_workflow_group_name}"
        return
    fi

    local last_starting_test_case_line_number
    last_starting_test_case_line_number="$(echo "${last_starting_test_case_line}" | cut -d':' -f1)"
    local composer_run_component_tests_log_lines_count
    composer_run_component_tests_log_lines_count="$(wc -l < "${composer_run_component_tests_log_file}")"
    local last_test_case_log_lines_count="$((composer_run_component_tests_log_lines_count-last_starting_test_case_line_number))"
    local last_test_case_log_file=/elastic_otel_php_tests/logs/composer_-_run_component_tests_-_last_test_case.log
    tail -n "${last_test_case_log_lines_count}" "${composer_run_component_tests_log_file}" > "${last_test_case_log_file}"

    local small_tail_lines_count=100
    local small_tail_log_file="/elastic_otel_php_tests/logs/composer_-_run_component_tests_-_last_${small_tail_lines_count}_lines.log"
    if [[ "${last_test_case_log_lines_count}" -gt "${small_tail_lines_count}" ]]; then
        tail -n "${small_tail_lines_count}" "${last_test_case_log_file}" > "${small_tail_log_file}"
    fi
    end_github_workflow_log_group "${current_workflow_group_name}"

    if [ -f "${last_test_case_log_file}" ]; then
        local current_workflow_group_name="The last test case's part of composer run_component_tests log (${last_test_case_log_lines_count} lines)"
        start_github_workflow_log_group "${current_workflow_group_name}"
        cat "${last_test_case_log_file}"
        end_github_workflow_log_group "${current_workflow_group_name}"
        return
    fi

    if [ -f "${small_tail_log_file}" ]; then
        local current_workflow_group_name="The last ${small_tail_lines_count} lines of composer run_component_tests log"
        start_github_workflow_log_group "${current_workflow_group_name}"
        cat "${small_tail_log_file}"
        end_github_workflow_log_group "${current_workflow_group_name}"
        return
    fi
}

function copy_syslog () {
    local current_workflow_group_name="Copying syslog files"
    start_github_workflow_log_group "${current_workflow_group_name}"

    local copy_syslog_to_dir=/elastic_otel_php_tests/logs/var_log
    mkdir -p "${copy_syslog_to_dir}"

    local -a syslog_prefix_candidates=(/var/log/syslog /var/log/messages)
    for syslog_prefix_candidate in "${syslog_prefix_candidates[@]}" ; do
        if ls "${syslog_prefix_candidate}"* 1> /dev/null 2>&1 ; then
            cp -r "${syslog_prefix_candidate}"* "${copy_syslog_to_dir}"
        fi
    done

    end_github_workflow_log_group "${current_workflow_group_name}"
}

function on_script_exit () {
    exitCode=$?

    copy_syslog

    if [[ "${exitCode}" -ne 0 ]]; then
        extract_log_related_to_failure
    fi

    local current_workflow_group_name="Content of /elastic_otel_php_tests/logs/"
    start_github_workflow_log_group "${current_workflow_group_name}"

    ls -l -R /elastic_otel_php_tests/logs/

    end_github_workflow_log_group "${current_workflow_group_name}"

    exit ${exitCode}
}

function main() {
    local current_workflow_group_name="Setting the environment for ${BASH_SOURCE[0]}"
    echo "::group::${current_workflow_group_name}"

    this_script_full_path="${BASH_SOURCE[0]}"
    this_script_dir="$( dirname "${this_script_full_path}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    echo "Current directory: ${PWD}"

    echo "ls -l"
    ls -l

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    start_syslog_and_set_related_config

    trap on_script_exit EXIT

    # Disable agent for auxiliary PHP processes to reduce noise in logs
    export ELASTIC_OTEL_ENABLED=false
    export OTEL_PHP_DISABLED_INSTRUMENTATIONS=all
    export OTEL_PHP_AUTOLOAD_ENABLED=false

    end_github_workflow_log_group "${current_workflow_group_name}"

    install_elastic_otel_package

    local current_workflow_group_name="Installing PHP dependencies using composer"
    start_github_workflow_log_group "${current_workflow_group_name}"

    if [ -f /repo_root/composer.lock ]; then
        rm -f /repo_root/composer.lock
    fi

    # Remove "open-telemetry/opentelemetry-auto-.*": lines from composer.json
    cp /repo_root/composer.json /repo_root/composer.json.original
    grep -v -E '"open-telemetry/opentelemetry-auto-.*":' /repo_root/composer.json.original > /repo_root/composer.json

    cat /repo_root/composer.json

    composer install

    end_github_workflow_log_group "${current_workflow_group_name}"

    /repo_root/tools/test/component/test_installed_package_one_matrix_row.sh
}

main "$@"
