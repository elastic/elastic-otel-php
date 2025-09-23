#!/bin/bash

RESULTS_PATH="${PWD}/build/otel_test_results"

show_help() {
    echo "Usage: $0 --build_architecture <architecture> --php_versions <versions> --results_path <path> --deb_package <package.deb> "
    echo
    echo "Arguments:"
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '81 82 83 84')."
    echo "  --results_path           Optional. The path where the results will be saved if a test failure occurs. (default is '${RESULTS_PATH}')"
    echo "  --deb_package            Optional. The path to debian package to install inside container."
    echo "  --quiet                  Optional. Quiet composer."
    echo
    echo "Example:"
    echo "  $0 --php_versions '81 82 83 84'"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
        --php_versions)
            PHP_VERSIONS=($2)
            shift
            ;;
        --results_path)
            RESULTS_PATH=($2)
            shift
            ;;
        --deb_package)
            PACKAGE=($2)
            shift
            ;;
        --quiet)
            QUIET=" -q "
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown parameter passed: $1"
            show_help
            exit 1
            ;;
        esac
        shift
    done
}

ROOT_PATH=${PWD}

parse_args "$@"

if [[ -z "$PHP_VERSIONS" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

echo "PHP_VERSIONS: ${PHP_VERSIONS[@]}"
echo "RESULTS PATH: ${RESULTS_PATH}"

TEST_ERROR=0

COMPOSE_FILE=${PWD}/docker/otel-tests/docker-compose.yml

for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    export PHP_VERSION="${PHP_VERSION:0:1}.${PHP_VERSION:1}"

    echo "::group::Building docker images"
    docker compose -f ${COMPOSE_FILE} build php
    echo "::endgroup::"

    echo "::group::Starting dependency containers"
    docker compose -f ${COMPOSE_FILE} up --force-recreate -d mysql postgresql
    echo "::endgroup::"

    VOLUMES=" -v ${PWD}:/source -v $(realpath ${RESULTS_PATH}):/results"
    COMMAND="/source/tools/build/test_otel_unit_tests.sh -f /source/composer.json -r /results -w /tmp/otel-test-run ${QUIET} -p open-telemetry/opentelemetry-auto-\*"

    if [ -n "${PACKAGE}" ]; then
        VOLUMES+=" -v $(realpath ${PACKAGE}):/package/package.deb "
        COMMAND="apt install /package/package.deb && ${COMMAND}"
    fi

    docker compose --progress plain -f ${COMPOSE_FILE} run --remove-orphans --rm ${VOLUMES} \
        -w /tmp php sh -c "${COMMAND}"

    ERRCODE=$?

    if [ $ERRCODE -ne 0 ]; then
        TEST_ERROR=$ERRCODE
    fi

    echo "::group::Stopping and removing containers"
    docker compose -f ${COMPOSE_FILE} stop
    docker compose -f ${COMPOSE_FILE} rm -f
    echo "::endgroup::"
done

if [[ $TEST_ERROR -ne 0 ]]; then
    echo "::error::At least one test failed"
    exit 1
fi
