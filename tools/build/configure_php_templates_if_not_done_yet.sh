#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

this_script_dir="$(dirname "${BASH_SOURCE[0]}")"
this_script_dir="$(realpath "${this_script_dir}")"

if ! [ -f "prod/php/ElasticOTel/Log/LogFeature.php" ] ; then
    "${this_script_dir}/configure_php_templates.sh"
fi
