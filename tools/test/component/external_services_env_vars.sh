#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

if [ -z "${OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET+x}" ] || [ "${OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET}" != "true" ] ; then
    export OTEL_PHP_TESTS_DOCKER_NETWORK=otel-php-distro-tests-component-network

    export OTEL_PHP_TESTS_MYSQL_HOST=otel-php-distro-tests-component-mysql
    export OTEL_PHP_TESTS_MYSQL_PORT=3306
    export OTEL_PHP_TESTS_MYSQL_USER=root
    export OTEL_PHP_TESTS_MYSQL_PASSWORD=otel-php-distro-tests-component-mysql-password
    export OTEL_PHP_TESTS_MYSQL_DB=OTEL_PHP_COMPONENT_TESTS_DB

    export OTEL_PHP_TESTS_EXTERNAL_SERVICES_DOCKER_COMPOSE_CMD_PREFIX="docker compose -f ${repo_root_dir:?}/tools/test/component/docker_compose_external_services.yml"

    export OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET=true
fi
