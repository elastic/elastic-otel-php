#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

# Source upstream shared.sh to get all upstream functions and utilities
source "${repo_root_dir:?}/upstream/tools/shared.sh"

# Override should_pass_env_var_to_docker to also pass ELASTIC_OTEL_* env vars
function should_pass_env_var_to_docker () {
    env_var_name_to_check="${1:?}"

    if [[ ${env_var_name_to_check} == "ELASTIC_OTEL_"* ]] || [[ ${env_var_name_to_check} == "OTEL_PHP_"* ]] || [[ ${env_var_name_to_check} == "OTEL_"* ]]; then
        echo "true"
        return
    fi

    echo "false"
}
