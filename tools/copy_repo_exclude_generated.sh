#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    local src_repo_root_dir="${1:?}"
    local dst_repo_root_dir="${2:?}"

    local rsync_opts=(--recursive --update)
    rsync_opts+=(--exclude '.git')
    rsync_opts+=(--filter=':- .gitignore')

    mkdir -p "${dst_repo_root_dir}"
    rsync "${rsync_opts[@]}" "${src_repo_root_dir}/" "${dst_repo_root_dir}/" > /dev/null
}

main "$@"
