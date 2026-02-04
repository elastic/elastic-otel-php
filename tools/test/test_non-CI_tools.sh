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
            edot_log "Unknown parameter passed: $1"
            show_help
            exit 1
            ;;
        esac
        shift
    done
}

function assert_not_empty_string() {
    local ARG_TO_CHECK="${1:?}"

    if [[ -z "${ARG_TO_CHECK}" ]]; then
        edot_log "${ARG_TO_CHECK} is an empty string"
        log_caller_stack_trace
        exit 1
    fi
}

function assert_file_dir_exist() {
    local FS_ITEM="${1:?}"

    if [[ ! -e "${FS_ITEM}" ]]; then
        edot_log "File/directory ${FS_ITEM} does not exist"
        exit 1
    fi
}

function assert_is_file() {
    local FS_ITEM="${1:?}"

    if [[ ! -f "${FS_ITEM}" ]]; then
        edot_log "${FS_ITEM} is not a regular file"
        exit 1
    fi
}

function assert_is_dir() {
    local FS_ITEM="${1:?}"

    if [[ ! -d "${FS_ITEM}" ]]; then
        edot_log "${FS_ITEM} is not a directory"
        exit 1
    fi
}

function compare_directories() {
    local LHS_DIR="${1:?}"
    local RHS_DIR="${2:?}"
    local CALL_ON_ITEMS="${3:?}"

    while IFS= read -d '' -r LHS_ITEM; do
        # Skip the current directory
        # SC2153: Possible misspelling: LHS_ITEM may not be assigned. Did you mean RHS_ITEM?
        # shellcheck disable=SC2153
        if [[ "${LHS_ITEM}" == "." ]]; then
            continue
        fi

        local REL_PATH
        REL_PATH=$(realpath -s --relative-to="${LHS_DIR}" "${LHS_ITEM}")

        local RHS_ITEM
        RHS_ITEM="${RHS_DIR}/${REL_PATH}"

        ${CALL_ON_ITEMS} "${LHS_ITEM}" "${RHS_ITEM}"
    done < <(find "${LHS_DIR}" -print0)
}

function test_generate_composer_lock_files_compare_file_dir() {
    local LHS_ITEM="${1:?}"
    local RHS_ITEM="${2:?}"

    assert_not_empty_string "${LHS_ITEM}"
    assert_not_empty_string "${RHS_ITEM}"
    assert_file_dir_exist "${LHS_ITEM}"
    assert_file_dir_exist "${RHS_ITEM}"

    if [[ -f "${LHS_ITEM}" ]]; then
        assert_is_file "${RHS_ITEM}"
        local LHS_ITEM_FILE_EXTENSION="${LHS_ITEM##*.}"
        if [[ "${LHS_ITEM_FILE_EXTENSION}" == 'json' ]]; then
            diff "${LHS_ITEM}" "${RHS_ITEM}"
        fi
    else
        assert_is_dir "${LHS_ITEM}"
        assert_is_dir "${RHS_ITEM}"
    fi
}

function test_generate_composer_lock_files() {
    # Prerequisite: Verify that root composer.json was not changed after the last time
    # tools/build/generate_composer_lock_files.sh was executed
    local dev_json_file_name
    dev_json_file_name="$(build_generated_composer_json_file_name "dev")"
    diff "${repo_root_dir}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}/${dev_json_file_name}" "${repo_root_dir}/composer.json"

    cd "${CURRENT_DIR_TO_RESTORE}"
    delete_dir_contents "${REPO_TEMP_COPY_DIR}"
    "${repo_root_dir}/tools/copy_repo_exclude_generated.sh" "${repo_root_dir}" "${REPO_TEMP_COPY_DIR}"

    cd "${REPO_TEMP_COPY_DIR}"
    "./tools/build/generate_composer_lock_files.sh"

    compare_directories \
        "${repo_root_dir}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}" \
        "${REPO_TEMP_COPY_DIR}/${elastic_otel_php_build_tools_composer_lock_files_dir_name:?}" \
        test_generate_composer_lock_files_compare_file_dir
}

function assert_same_file_dir() {
    local LHS_ITEM="${1:?}"
    local RHS_ITEM="${2:?}"

    assert_not_empty_string "${LHS_ITEM}"
    assert_not_empty_string "${RHS_ITEM}"
    assert_file_dir_exist "${LHS_ITEM}"
    assert_file_dir_exist "${RHS_ITEM}"

    if [[ -f "${LHS_ITEM}" ]]; then
        assert_is_file "${RHS_ITEM}"
        diff "${LHS_ITEM}" "${RHS_ITEM}"
    else
        assert_is_dir "${LHS_ITEM}"
        assert_is_dir "${RHS_ITEM}"
    fi
}

function test_generate_source_code_files_for_OpAMP_spec_protobuf() {
    cd "${CURRENT_DIR_TO_RESTORE}"
    delete_dir_contents "${REPO_TEMP_COPY_DIR}"
    "${repo_root_dir}/tools/copy_repo_exclude_generated.sh" "${repo_root_dir}" "${REPO_TEMP_COPY_DIR}"

    cd "${REPO_TEMP_COPY_DIR}"
    "./tools/test/component/generate_source_code_files_for_OpAMP_spec_protobuf.sh"

    # The value should be the same as the last part of GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE
    # in tools/test/component/generate_source_code_files_for_OpAMP_spec_protobuf.sh
    local PHP_NAMESPACE_LAST_PART='OpampProto'

    compare_directories \
        "${repo_root_dir}/${elastic_otel_php_tests_generated_source_code_dir_rel_path:?}/${PHP_NAMESPACE_LAST_PART}" \
        "${REPO_TEMP_COPY_DIR}/${elastic_otel_php_tests_generated_source_code_dir_rel_path:?}/${PHP_NAMESPACE_LAST_PART}" \
        assert_same_file_dir
}

function on_script_exit() {
    if [ -n "${CURRENT_DIR_TO_RESTORE+x}" ]; then
        cd "${CURRENT_DIR_TO_RESTORE}"
    fi

    if [ -n "${REPO_TEMP_COPY_DIR+x}" ] && [ -d "${REPO_TEMP_COPY_DIR}" ]; then
        delete_temp_dir "${REPO_TEMP_COPY_DIR}"
    fi
}

main() {
    CURRENT_DIR_TO_RESTORE="${PWD}"

    # tools/shared.sh" expects repo_root_dir to be defined
    repo_root_dir="$(realpath "${PWD}")"
    source "${repo_root_dir}/tools/shared.sh"

    # Parse arguments
    parse_args "$@"

    trap on_script_exit EXIT

    REPO_TEMP_COPY_DIR="$(mktemp -d)"
    edot_log "REPO_TEMP_COPY_DIR: ${REPO_TEMP_COPY_DIR}"

    test_generate_composer_lock_files
    test_generate_source_code_files_for_OpAMP_spec_protobuf
}

main "$@"
