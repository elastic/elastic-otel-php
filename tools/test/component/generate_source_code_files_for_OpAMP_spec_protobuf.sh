#!/usr/bin/env bash
set -e -o pipefail
#set -x

# You can find which version are released at https://github.com/open-telemetry/opamp-spec/releases
OpAMP_spec_release_version=0.14.0
OpAMP_spec_release_tag="v${OpAMP_spec_release_version}"

function fetch_proto_files () {
    local dest_dir="$1"
    local repo_source_temp_dir="${dest_dir}/repo_source"
    mkdir -p "${repo_source_temp_dir}"
    github_download_release_source_code_by_tag "open-telemetry" "opamp-spec" "${OpAMP_spec_release_tag}" "${repo_source_temp_dir}"
    cp --recursive --no-target-directory "${repo_source_temp_dir}/proto" "${dest_dir}"
    rm -rf "${repo_source_temp_dir}"
}

function generate_readme () {
    local this_script_abs_path="${BASH_SOURCE[0]}"
    local this_script_path_relative_to_repo_root
    this_script_path_relative_to_repo_root=$(realpath -s --relative-to="${repo_root_dir}" "${this_script_abs_path}")

    cat << EOL_marker_f6f9d3ac391044db93f271e9a459a9aa >> "${generated_source_code_files_dir}/README.md"
# This directory contains generated files.
# DO NOT EDIT!

The files were generated from .proto files at
        https://github.com/open-telemetry/opamp-spec
    tag
        ${OpAMP_spec_release_tag}

To update the generated files, update the following script and run it from the root of the repo:
\`\`\`
"./${this_script_path_relative_to_repo_root}"
\`\`\`
EOL_marker_f6f9d3ac391044db93f271e9a459a9aa
}

function main () {
    this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
    this_script_dir="$(realpath "${this_script_dir}")"

    repo_root_dir="$(realpath "${this_script_dir}/../../..")"
    source "${repo_root_dir}/tools/shared.sh"

    generated_source_code_files_dir="${repo_root_dir}/tests/generated_source_code/OpAMP_spec_protobuf"
    rm -rf "${generated_source_code_files_dir}"
    mkdir -p "${generated_source_code_files_dir}"

    local temp_dir="${generated_source_code_files_dir}/TEMP"
    mkdir -p "${temp_dir}"

    local dot_proto_files_temp_dir="${temp_dir}/dot_proto_files"
    mkdir -p "${dot_proto_files_temp_dir}"
    fetch_proto_files "${dot_proto_files_temp_dir}"

    generate_PHP_source_code_files_from_dot_proto "${dot_proto_files_temp_dir}" "${generated_source_code_files_dir}"

    rm -rf "${temp_dir}"

    generate_readme
}

main "$@"
