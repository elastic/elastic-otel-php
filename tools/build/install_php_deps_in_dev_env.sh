#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

./tools/build/configure_php_templates.sh

php ./tools/build/install_php_deps_in_dev_env_helper.php
