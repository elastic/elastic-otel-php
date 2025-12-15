#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

# You can find which version are released at https://github.com/open-telemetry/opamp-spec/releases
OPAMP_SPEC_RELEASE_VERSION=0.14.0
OPAMP_SPEC_RELEASE_TAG="v${OPAMP_SPEC_RELEASE_VERSION}"

GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE='ElasticOTelTests\Generated\OpampProto'

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
    if [ -z "${ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES+x}" ]; then
        export ELASTIC_OTEL_PHP_TOOLS_KEEP_TEMP_FILES="false"
    fi

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
            edot_log "Unknown parameter passed: $1"
            show_help
            exit 1
            ;;
        esac
        shift
    done
}

function fetch_proto_files () {
    local DST_DIR="$1"

    local OPAMP_SPEC_ORG="open-telemetry"
    local OPAMP_SPEC_REPO_NAME="opamp-spec"
    local OPAMP_SPEC_REPO_TEMP_DIR="${DST_DIR}/${OPAMP_SPEC_ORG}_${OPAMP_SPEC_REPO_NAME}_repo"
    mkdir -p "${OPAMP_SPEC_REPO_TEMP_DIR}"
    github_download_release_source_code_by_tag "${OPAMP_SPEC_ORG}" "${OPAMP_SPEC_REPO_NAME}" "${OPAMP_SPEC_RELEASE_TAG}" "${OPAMP_SPEC_REPO_TEMP_DIR}"
    move_dir_contents "${OPAMP_SPEC_REPO_TEMP_DIR}/proto" "${DST_DIR}"
    delete_temp_dir "${OPAMP_SPEC_REPO_TEMP_DIR}"
}

function adapt_dot_proto_files () {
    local DOT_PROTO_FILES_DIR="$1"

    local OPTIONS_TO_APPEND=""
    OPTIONS_TO_APPEND+="option php_namespace = \"${GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE}\";"
    OPTIONS_TO_APPEND+=" "
    OPTIONS_TO_APPEND+="option php_metadata_namespace = \"${GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE}\\Metadata\";"
    # Escape \ twice - one time for bash and another for sed
    OPTIONS_TO_APPEND=${OPTIONS_TO_APPEND//\\/\\\\}
    OPTIONS_TO_APPEND=${OPTIONS_TO_APPEND//\\/\\\\}
    OPTIONS_TO_APPEND=${OPTIONS_TO_APPEND//\"/\\\"}

    for DOT_PROTO_FILE in "${DOT_PROTO_FILES_DIR}/"*.proto; do
        edot_log "Before adapting file: ${DOT_PROTO_FILE}"
        grep -E -A1 '^package [a-zA-Z0-9.]+;$' "${DOT_PROTO_FILE}" 1>&2

        # sed '/[pattern]/a [new line of text]' filename
        # This command finds lines containing [pattern] and appends [new line of text] on a new line immediately following the match
        #
        # Append
        # option php_namespace = "${PHP_NAMESPACE_AS_PROTO_PACKAGE}";
        # option php_metadata_namespace = "${PHP_NAMESPACE_AS_PROTO_PACKAGE}\\Metadata";

        sed -i "/package opamp.proto;/a ${OPTIONS_TO_APPEND}" "${DOT_PROTO_FILE}"

        edot_log "After adapting file: ${DOT_PROTO_FILE}"
        grep -E -A1 '^package [a-zA-Z0-9.]+;$' "${DOT_PROTO_FILE}" 1>&2
    done
}

function adapt_generated_PHP_source_code_files () {
    local GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT="$1"
    local GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR="$2"

    # Replace \ with /
    local SUB_DIR_PATH_FOR_PHP_NAMESPACE=${GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE//\\//}

    move_dir_contents "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT}/${SUB_DIR_PATH_FOR_PHP_NAMESPACE}" "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR}"
}

function generate_readme () {
    local THIS_SCRIPT_PATH_RELATIVE_TO_REPO_ROOT="$1"
    local DEST_README_FILE="$2"

    cat << EOL_marker_f6f9d3ac391044db93f271e9a459a9aa >> "${DEST_README_FILE}"
# This directory contains generated files.
# DO NOT EDIT!

The files were generated from .proto files at
        https://github.com/open-telemetry/opamp-spec
    tag
        ${OPAMP_SPEC_RELEASE_TAG}

To update the generated files, update the following script and run it from the root of the repo:
\`\`\`
"./${THIS_SCRIPT_PATH_RELATIVE_TO_REPO_ROOT}"
\`\`\`
EOL_marker_f6f9d3ac391044db93f271e9a459a9aa
}

function move_generated_files_from_stage_to_final_dest_dir() {
    local SRC_DIR="$1"
    local DST_DIR="$2"

    mkdir -p "${DST_DIR}"
    delete_dir_contents "${DST_DIR}"
    move_dir_contents "${SRC_DIR}" "${DST_DIR}"
}

function on_script_exit() {
    if [ -n "${TEMP_STAGE_DIR+x}" ] && [ -d "${TEMP_STAGE_DIR}" ]; then
        delete_temp_dir "${TEMP_STAGE_DIR}"
    fi
}

function main () {
    # tools/shared.sh" expects repo_root_dir to be defined
    repo_root_dir="$(realpath "${PWD}")"
    source "${repo_root_dir}/tools/shared.sh"

    parse_args "$@"

    # TEMP_STAGE_DIR is not local because it's referenced in on_script_exit and we cannot pass it as parameter
    TEMP_STAGE_DIR="$(mktemp -d)"
    edot_log "TEMP_STAGE_DIR: ${TEMP_STAGE_DIR}"

    trap on_script_exit EXIT

    local DOT_PROTO_FILES_DIR="${TEMP_STAGE_DIR}/dot_proto_files"
    mkdir -p "${DOT_PROTO_FILES_DIR}"
    fetch_proto_files "${DOT_PROTO_FILES_DIR}"

    adapt_dot_proto_files "${DOT_PROTO_FILES_DIR}"

    local GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT="${TEMP_STAGE_DIR}/${elastic_otel_php_tests_generated_source_code_dir_rel_path:?}_before_adapt"
    mkdir -p "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT}"
    generate_PHP_source_code_files_from_dot_proto "${DOT_PROTO_FILES_DIR}" "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT}"

    local GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR="${TEMP_STAGE_DIR}/${elastic_otel_php_tests_generated_source_code_dir_rel_path:?}"
    mkdir -p "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR}"
    adapt_generated_PHP_source_code_files "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT}" "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR}"
    delete_temp_dir "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR_BEFORE_ADAPT}"

    local THIS_SCRIPT_ABS_PATH="${BASH_SOURCE[0]}"
    THIS_SCRIPT_ABS_PATH="$(realpath "${THIS_SCRIPT_ABS_PATH}")"
    local THIS_SCRIPT_PATH_RELATIVE_TO_REPO_ROOT
    THIS_SCRIPT_PATH_RELATIVE_TO_REPO_ROOT=$(realpath -s --relative-to="${repo_root_dir}" "${THIS_SCRIPT_ABS_PATH}")
    generate_readme "${THIS_SCRIPT_PATH_RELATIVE_TO_REPO_ROOT}" "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR}/README.md"

    # Replace \ with /
    local SUB_DIR_PATH_FOR_PHP_NAMESPACE=${GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE//\\//}
    local PHP_NAMESPACE_LAST_PART
    PHP_NAMESPACE_LAST_PART="$(basename "${SUB_DIR_PATH_FOR_PHP_NAMESPACE}")"
    # elastic_otel_php_tests_generated_source_code_dir_rel_path is defined in tools/shared.sh
    move_generated_files_from_stage_to_final_dest_dir "${GENERATED_SOURCE_CODE_FILES_TEMP_STAGE_DIR}" "${repo_root_dir}/${elastic_otel_php_tests_generated_source_code_dir_rel_path:?}/${PHP_NAMESPACE_LAST_PART}"

    # No need for delete_temp_dir "${TEMP_STAGE_DIR}" - ${TEMP_STAGE_DIR} is deleted in on_script_exit()
}

main "$@"
