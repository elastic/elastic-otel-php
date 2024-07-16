#!/bin/bash

set -x

show_help() {
    echo "Usage: $0 --build_architecture <architecture> --php_versions <versions> --results_path <path>"
    echo
    echo "Arguments:"
    echo "  --build_architecture     Required. Build architecture (e.g., 'linux-x86-64')."
    echo "  --php_versions           Required. List of PHP versions separated by spaces (e.g., '80 81 82 83')."
    echo "  --results_path           Optional. The path where the results will be saved if a test failure occurs. (default is 'build/test_phpt_results')"
    echo
    echo "Example:"
    echo "  $0 --build_architecture linux-x86-64 --php_versions '80 81 82 83'"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --build_architecture)
                BUILD_ARCHITECTURE="$2"
                shift
                ;;
            --php_versions)
                PHP_VERSIONS=($2)
                shift
                ;;
            --results_path)
                RESULTS_PATH=($2)
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

RESULTS_PATH="${PWD}/build/test_phpt_results"

parse_args "$@"

if [[ -z "$BUILD_ARCHITECTURE" ]] || [[ -z "$PHP_VERSIONS" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

echo "BUILD_ARCHITECTURE: $BUILD_ARCHITECTURE"
echo "PHP_VERSIONS: ${PHP_VERSIONS[@]}"
echo "RESULTS PATH: ${RESULTS_PATH}"

TEST_ERROR=0
mkdir -p "${RESULTS_PATH}"

pushd prod/native/extension/phpt
    mkdir -p ${PWD}/results

    for PHP_VERSION in "${PHP_VERSIONS[@]}"
    do
        ./run.sh -b ${BUILD_ARCHITECTURE} -p ${PHP_VERSION} -f ${RESULTS_PATH}/phpt-${PHP_VERSION}.tar.gz || TEST_ERROR=1
    done
popd

pushd prod/native/phpbridge_extension/phpt
    mkdir -p ${PWD}/results

    for PHP_VERSION in "${PHP_VERSIONS[@]}"
    do
        ./run.sh -b ${BUILD_ARCHITECTURE} -p ${PHP_VERSION} -f ${RESULTS_PATH}/phpt-bridge-${PHP_VERSION}.tar.gz || TEST_ERROR=1
    done
popd

if [ $TEST_ERROR -ne 0 ]; then
    echo "Some tests failed"
    exit 1
fi
