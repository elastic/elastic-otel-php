#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    this_script_dir="$( dirname "${BASH_SOURCE[0]}" )"
    this_script_dir="$( realpath "${this_script_dir}" )"
    src_repo_root_dir="$(realpath "${this_script_dir}/../../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    source "${this_script_dir}/external_services_env_vars.sh"

    local current_github_workflow_log_group_name="Starting external services used by component tests"
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    run_command_with_timeout_and_retries_args=(--retry-on-error=yes)
    run_command_with_timeout_and_retries_args=(--max-tries=3 "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--wait-time-before-retry=60 "${run_command_with_timeout_and_retries_args[@]}")
    run_command_with_timeout_and_retries_args=(--increase-wait-time-before-retry-exponentially=yes "${run_command_with_timeout_and_retries_args[@]}")
    # SC2086: <var> might contain multiple space separated arguments
    # shellcheck disable=SC2086
    "${src_repo_root_dir}/tools/run_command_with_timeout_and_retries.sh" "${run_command_with_timeout_and_retries_args[@]}" -- \
        ${ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_DOCKER_COMPOSE_CMD_PREFIX:?} up -d

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"
}

main "$@"
