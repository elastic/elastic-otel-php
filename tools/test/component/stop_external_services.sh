#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"
    src_repo_root_dir="$(realpath "${this_script_dir}/../../..")"

    source "${src_repo_root_dir}/tools/shared.sh"

    source "${this_script_dir}/external_services_env_vars.sh"

    local current_github_workflow_log_group_name="Stopping external services used by component tests"
    start_github_workflow_log_group "${current_github_workflow_log_group_name}"

    ${ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_DOCKER_COMPOSE_CMD_PREFIX:?} down -v --remove-orphans

    end_github_workflow_log_group "${current_github_workflow_log_group_name}"
}

main "$@"
