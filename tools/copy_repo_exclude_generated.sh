#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

function main() {
    local src_repo_root_dir="${1:?}"
    local dst_repo_root_dir="${2:?}"

    local rsync_opts=(--recursive --update)
    rsync_opts+=(--exclude '.git')
    rsync_opts+=(--exclude '.idea')
    rsync_opts+=(--exclude '/_build/')
    rsync_opts+=(--exclude '/build/')
    rsync_opts+=(--exclude '/composer.lock')
    rsync_opts+=(--exclude '/composer_prod.lock')
    rsync_opts+=(--exclude '/prod/php/vendor_*')
    rsync_opts+=(--exclude '/vendor/')
    rsync_opts+=(--exclude '/vendor_prod/')
    rsync_opts+=(--exclude '/z_local*/')

    mkdir -p "${dst_repo_root_dir}"
    rsync "${rsync_opts[@]}" "${src_repo_root_dir}/" "${dst_repo_root_dir}/" > /dev/null

#    local sub_dirs_to_copy=(
#        "${elastic_otel_php_generated_composer_lock_files_dir_name:?}" \
#        "prod/php/ElasticOTel" \
#        "prod/php/OpenTelemetry" \
#    )
#
#    for file_to_copy in "${files_to_copy[@]:?}" ; do
#        local src_file_to_copy="${src_repo_root_dir}/${file_to_copy}"
#        if ! [ -f "${src_file_to_copy}" ] ; then
#            continue
#        fi
#        local dst_file_to_copy="${dst_repo_root_dir}/${file_to_copy}"
#        mkdir -p "$(dirname "${dst_file_to_copy}")"
#        cp "${src_file_to_copy}" "${dst_file_to_copy}"
#    done
#
##    "/" \
##    "tools/" \
##    "tests/"
#
#    for file_to_copy in "${files_to_copy[@]:?}" ; do
#        mkdir -p "$(dirname "${dst_repo_root_dir}/${file_to_copy}")"
#        cp "${src_repo_root_dir}/${file_to_copy}" "${dst_repo_root_dir}/${file_to_copy}"
#    done
#
#    local files_to_copy=(
#        "composer.json" \
#        "composer_prod.json" \
#        "elastic-otel-php.properties" \
#        "phpcs.xml" \
#        "phpstan.dist.neon" \
#        "phpunit.xml" \
#        "phpunit_component_tests.xml" \
#        "prod/php/bootstrap_php_part.php" \
#    )
#
#    for file_to_copy in "${files_to_copy[@]:?}" ; do
#        local src_file_to_copy="${src_repo_root_dir}/${file_to_copy}"
#        if ! [ -f "${src_file_to_copy}" ] ; then
#            continue
#        fi
#        local dst_file_to_copy="${dst_repo_root_dir}/${file_to_copy}"
#        mkdir -p "$(dirname "${dst_file_to_copy}")"
#        cp "${src_file_to_copy}" "${dst_file_to_copy}"
#    done
}

main "$@"
