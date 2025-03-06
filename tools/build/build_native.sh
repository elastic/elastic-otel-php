#!/bin/bash

set -x

SKIP_CONFIGURE=false

show_help() {
    echo "Usage: $0 --build_architecture <architecture> [--ncpu <num_cpus>] [--conan_cache_path <conan_cache_path>] [--skip_configure] [--skip_unit_tests]"
    echo
    echo "Arguments:"
    echo "  --build_architecture    Required. Build architecture (e.g., 'linux-x86-64')."
    echo "  --ncpu                  Optional. Number of CPUs to use for building. Default is one less than the installed CPUs."
    echo "  --conan_cache_path      Optional. Path to local cache for Conan."
    echo "  --skip_configure        Optional. Skip the configuration step."
    echo "  --interactive           Optional. Run container in interactive mode."
    echo "  --skip_unit_tests       Optional. Skip unit tests. Default is to run unit tests."
    echo
    echo "Example:"
    echo "  $0 --build_architecture linux-x86-64 --ncpu 4 --conan_cache_path ~/.conan_cache --skip_configure"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --build_architecture)
                BUILD_ARCHITECTURE="$2"
                shift
                ;;
            --ncpu)
                NCPU=" -j$2 "
                shift
                ;;
            --conan_cache_path)
                CONAN_CACHE_PATH="$2"
                shift
                ;;
            --skip_configure)
                SKIP_CONFIGURE=true
                ;;
            --interactive)
                INTERACTIVE=" -i "
                ;;
            --skip_unit_tests)
                SKIP_UNIT_TESTS=true
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

parse_args "$@"

if [[ -z "$BUILD_ARCHITECTURE" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

# Building mount point and environment if ${CONAN_CACHE_PATH} not empty
if [[ -n "${CONAN_CACHE_PATH}" ]]; then
    echo "CONAN_CACHE_PATH: ${CONAN_CACHE_PATH}"
    # due safety not mounting user home folder but only .conan
    mkdir -p "${CONAN_CACHE_PATH}"
    # https://docs.conan.io/2/reference/environment.html#conan-home
    CONAN_HOME_MP=(-e "CONAN_HOME=/conan_home" -v "${CONAN_CACHE_PATH}:/conan_home")
fi

echo "BUILD_ARCHITECTURE: $BUILD_ARCHITECTURE"
echo "NCPU: $NCPU"
echo "SKIP_CONFIGURE: $SKIP_CONFIGURE"

if [ "$SKIP_CONFIGURE" = true ]; then
    echo "Skipping configuration step..."
else
    CONFIGURE="cmake --preset ${BUILD_ARCHITECTURE}-release  && "
fi

if [ "$GITHUB_ACTIONS" = true ]; then
    USERID=" -u : "
else
    USERID=" -u $(id -u):$(id -g) "
fi

if [ "$SKIP_UNIT_TESTS" = true ]; then
    UNIT_TESTS="echo \"Skipped unit tests (SKIP_UNIT_TESTS: $SKIP_UNIT_TESTS).\""
else
    UNIT_TESTS="ctest --preset ${BUILD_ARCHITECTURE}-release --verbose"
fi

ls -al "${PWD}"

docker run --rm -t ${INTERACTIVE} ${USERID} -v ${PWD}:/source \
    "${CONAN_HOME_MP[@]}" \
    -w /source/prod/native \
    -e GITHUB_SHA=${GITHUB_SHA} \
    elasticobservability/apm-agent-php-dev:native-build-gcc-14.2.0-${BUILD_ARCHITECTURE}-0.0.1 \
    sh -c "id && echo CONAN_HOME: \$CONAN_HOME && ${CONFIGURE} cmake --build --preset ${BUILD_ARCHITECTURE}-release ${NCPU} && ${UNIT_TESTS}"

