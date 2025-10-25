#!/usr/bin/env bash
set -e -o pipefail
#set -x

function print_info_about_environment () {
    echo "Current directory: ${PWD}"

    echo "ls -l"
    ls -l

    echo 'Set environment variables (env):'
    env | sort

    echo -n 'PHP version (php -v):'
    php -v

    echo 'Installed PHP extensions (php -m):'
    php -m

    echo 'PHP info (php -i):'
    php -i

    echo -n "php -r \"echo ini_get('memory_limit');\" => "
    php -r "echo ini_get('memory_limit') . PHP_EOL;"
}

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
    local current_github_workflow_log_group_name="Installing package with Elastic OTel for PHP distro"
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    # Until we add testing for ARM architecture is hardcoded as x86_64
    local architecture="x86_64"

    local package_file_full_path
    package_file_full_path=$(select_elastic_otel_package_file /elastic_otel_php_tests/packages "${ELASTIC_OTEL_PHP_TESTS_PACKAGE_TYPE:?}" "${architecture}")

    install_package_file "${package_file_full_path}"

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"
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
    if ps -ef | grep syslogd | grep -v grep &> /dev/null ; then
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

function extract_log_ending () {
    if [ ! -f "${composer_run_component_tests_log_file}" ]; then
        end_github_workflow_log_group "${current_github_workflow_log_group_name}"
        return
    fi

    # If we find at least one test case start line then we extract log lines from the last test case start line
    # If the number of lines extracted above is no more than ${small_tail_lines_count} we print them and return
    # If there are no test case start lines or the number of lines extracted above is more than ${small_tail_lines_count}
    # then we print only the last ${small_tail_lines_count} lines of the log

    last_test_case_log_lines_count=-1
    last_test_case_log_file=/elastic_otel_php_tests/logs/composer_-_run_component_tests_-_last_test_case.log
    local last_starting_test_case_line=''
    if grep -n -E "^Starting test case: " "${composer_run_component_tests_log_file}" &> /dev/null ; then
        last_starting_test_case_line="$(grep -n -E "^Starting test case: " "${composer_run_component_tests_log_file}" | tail -1)"
    fi

    if [[ -n "${last_starting_test_case_line}" ]]; then # -n is true if string is not empty
        local last_starting_test_case_line_number
        last_starting_test_case_line_number="$(echo "${last_starting_test_case_line}" | cut -d':' -f1)"
        local composer_run_component_tests_log_lines_count
        composer_run_component_tests_log_lines_count="$(wc -l < "${composer_run_component_tests_log_file}")"
        # grep starts line numbers from 1 so we need to add 1
        last_test_case_log_lines_count="$((composer_run_component_tests_log_lines_count - last_starting_test_case_line_number + 1))"
        tail -n "${last_test_case_log_lines_count}" "${composer_run_component_tests_log_file}" > "${last_test_case_log_file}"
    fi

    small_tail_lines_count=100
    small_tail_log_file="/elastic_otel_php_tests/logs/composer_-_run_component_tests_-_last_${small_tail_lines_count}_lines.log"

    if [[ "${last_test_case_log_lines_count}" -eq -1 ]] || [[ "${last_test_case_log_lines_count}" -gt "${small_tail_lines_count}" ]]; then
        tail -n "${small_tail_lines_count}" "${composer_run_component_tests_log_file}" > "${small_tail_log_file}"
    fi
}

function copy_syslog () {
    local copy_syslog_to_dir=/elastic_otel_php_tests/logs/var_log
    mkdir -p "${copy_syslog_to_dir}"

    local -a syslog_prefix_candidates=(/var/log/syslog /var/log/messages)
    for syslog_prefix_candidate in "${syslog_prefix_candidates[@]}" ; do
        if ls "${syslog_prefix_candidate}"* 1> /dev/null 2>&1 ; then
            cp -r "${syslog_prefix_candidate}"* "${copy_syslog_to_dir}"
        fi
    done
}

function print_log_ending () {
    if [ -f "${last_test_case_log_file}" ] ; then
        if [[ "${last_test_case_log_lines_count}" -gt "${small_tail_lines_count}" ]]; then
            echo "The last test case part of composer run_component_tests log (${last_test_case_log_lines_count} lines) extracted to ${last_test_case_log_file}"
        else
            local current_github_workflow_log_group_name="The last test case part of composer run_component_tests log (${last_test_case_log_lines_count} lines)"
            start_github_workflow_log_group "${current_github_workflow_log_group_name}"
            cat "${last_test_case_log_file}"
            end_github_workflow_log_group "${current_github_workflow_log_group_name}"
        fi
    fi

    if [ -f "${small_tail_log_file}" ] ; then
        local current_github_workflow_log_group_name="The last ${small_tail_lines_count} lines of composer run_component_tests log"
        start_github_workflow_log_group "${current_github_workflow_log_group_name}"
        cat "${small_tail_log_file}"
        end_github_workflow_log_group "${current_github_workflow_log_group_name}"
    fi
}

function gather_logs () {
    copy_syslog

    extract_log_ending

    # Setting ownership/permissions to allow docker host to read files copied to /elastic_otel_php_tests/logs/
    chown -R "${ELASTIC_OTEL_PHP_TESTS_DOCKER_RUNNING_USER_ID:?}:${ELASTIC_OTEL_PHP_TESTS_DOCKER_RUNNING_USER_GROUP_ID:?}" /elastic_otel_php_tests/logs
    chmod -R 777 /elastic_otel_php_tests/logs

    current_github_workflow_log_group_name="Content of /elastic_otel_php_tests/logs after setting ownership/permissions"
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    ls -ld /elastic_otel_php_tests/logs
    ls -l -R /elastic_otel_php_tests/logs/

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"
}

function on_script_exit () {
    local exit_code=$?

    # End the workflow log group started in main()
    end_github_workflow_log_group "${current_github_workflow_log_group_name}"

    gather_logs
    print_log_ending

    exit ${exit_code}
}

function main() {
    current_github_workflow_log_group_name="Setting the environment for ${BASH_SOURCE[0]}"
    echo "::group::${current_github_workflow_log_group_name}"

    this_script_full_path="${BASH_SOURCE[0]}"
    this_script_dir="$( dirname "${this_script_full_path}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    echo 'Before setting PHP_INI_SCAN_DIR'
    print_info_about_environment

    if [[ -z "${PHP_INI_SCAN_DIR}" ]]; then
        # If you include an empty path segment (i.e., with a leading colon),
        # PHP will also scan the directory specified during compilation (via the --with-config-file-scan-dir option).
        # :/some_dir scans the compile-time directory and then /some_dir
        export PHP_INI_SCAN_DIR=:/elastic_otel_php_tests/php_ini_scan_dir
    else
        export PHP_INI_SCAN_DIR=${PHP_INI_SCAN_DIR}:/elastic_otel_php_tests/php_ini_scan_dir
    fi
    echo "ls -l /elastic_otel_php_tests/php_ini_scan_dir"
    ls -l /elastic_otel_php_tests/php_ini_scan_dir

    echo 'After setting PHP_INI_SCAN_DIR'
    print_info_about_environment

    repo_root_dir="$( realpath "${this_script_dir}/../../.." )"
    source "${repo_root_dir}/tools/shared.sh"

    start_syslog_and_set_related_config

    export composer_run_component_tests_log_file
    composer_run_component_tests_log_file=/elastic_otel_php_tests/logs/composer_-_run_component_tests.log

    trap on_script_exit EXIT

    # Disable agent for auxiliary PHP processes to reduce noise in logs
    export ELASTIC_OTEL_ENABLED=false
    export OTEL_PHP_DISABLED_INSTRUMENTATIONS=all
    export OTEL_PHP_AUTOLOAD_ENABLED=false

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"

    install_elastic_otel_package
    echo 'After installing Elastic OTel (EDOT)'
    print_info_about_environment

    current_github_workflow_log_group_name="Installing PHP dependencies using composer"
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    cp -f /composer_to_use.json ./composer.json
    cp -f /composer_to_use.lock ./composer.lock
    rm -rf ./vendor/ ./prod/php/vendor_*/
    composer --check-lock --no-check-all validate
    ELASTIC_OTEL_TOOLS_ALLOW_DIRECT_COMPOSER_COMMAND=true composer --no-interaction install

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"

    current_github_workflow_log_group_name="Running component tests for app_host_kind: ${ELASTIC_OTEL_PHP_TESTS_APP_CODE_HOST_KIND}"
    if [[ -n "${ELASTIC_OTEL_PHP_TESTS_GROUP}" ]]; then # -n is true if string is not empty
        current_github_workflow_log_group_name="${current_github_workflow_log_group_name}, test_group: ${ELASTIC_OTEL_PHP_TESTS_GROUP}"
    fi
    if [[ -n "${ELASTIC_OTEL_PHP_TESTS_FILTER}" ]]; then # -n is true if string is not empty
        current_github_workflow_log_group_name="${current_github_workflow_log_group_name}, filter: ${ELASTIC_OTEL_PHP_TESTS_FILTER}"
    fi
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    export ELASTIC_OTEL_PHP_TESTS_LOGS_DIRECTORY="/elastic_otel_php_tests/logs"
    /repo_root/tools/test/component/test_installed_package_one_matrix_row.sh
}

main "$@"
