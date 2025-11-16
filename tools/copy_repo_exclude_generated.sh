#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    local src_repo_root_dir="${1:?}"
    local dst_repo_root_dir="${2:?}"

    local rsync_opts=(--recursive --update)
    rsync_opts+=(--exclude '.git')
    rsync_opts+=(--exclude '.idea')
    rsync_opts+=(--exclude '/build/')
    rsync_opts+=(--exclude '/composer.lock')
    rsync_opts+=(--exclude '/composer_prod.lock')
    rsync_opts+=(--exclude '/prod/native/_build/')
    rsync_opts+=(--exclude '/prod/php/vendor_*/')
    rsync_opts+=(--exclude '/vendor/')
    rsync_opts+=(--exclude '/vendor_prod/')
    rsync_opts+=(--exclude '/z_local*')

    mkdir -p "${dst_repo_root_dir}"
    rsync "${rsync_opts[@]}" "${src_repo_root_dir}/" "${dst_repo_root_dir}/" > /dev/null
}

main "$@"
