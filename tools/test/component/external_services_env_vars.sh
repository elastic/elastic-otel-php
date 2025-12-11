#!/usr/bin/env bash
set -e -u -o pipefail
#set -x

if [ -z "${ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET+x}" ] || [ "${ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET}" != "true" ] ; then
    export ELASTIC_OTEL_PHP_TESTS_DOCKER_NETWORK=elastic-otel-php-tests-component-network

    export ELASTIC_OTEL_PHP_TESTS_MYSQL_HOST=elastic-otel-php-tests-component-mysql
    export ELASTIC_OTEL_PHP_TESTS_MYSQL_PORT=3306
    export ELASTIC_OTEL_PHP_TESTS_MYSQL_USER=root
    export ELASTIC_OTEL_PHP_TESTS_MYSQL_PASSWORD=elastic-otel-php-tests-component-mysql-password
    export ELASTIC_OTEL_PHP_TESTS_MYSQL_DB=ELASTIC_OTEL_PHP_COMPONENT_TESTS_DB

    export ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_DOCKER_COMPOSE_CMD_PREFIX="docker compose -f ${repo_root_dir:?}/tools/test/component/docker_compose_external_services.yml"

    export ELASTIC_OTEL_PHP_TESTS_EXTERNAL_SERVICES_ENV_VARS_ARE_SET=true
fi
