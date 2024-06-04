#!/bin/bash

NCPU=$(($(nproc) - 1))
CONAN_USER_HOME=""
SKIP_CONFIGURE=false

show_help() {
    echo "Usage: $0 --build_architecture <architecture> [--ncpu <num_cpus>] [--CONAN_USER_HOME <cache_path>] [--skip_configure]"
    echo
    echo "Arguments:"
    echo "  --build_architecture     Required. Build architecture (e.g., 'linux-x86-64')."
    echo "  --ncpu                   Optional. Number of CPUs to use for building. Default is one less than the installed CPUs."
    echo "  --conan_user_home        Optional. Path to local user cache for Conan. Default is empty."
    echo "  --skip_configure         Optional. Skip the configuration step."
    echo
    echo "Example:"
    echo "  $0 --build_architecture linux-x86-64 --ncpu 4 --conan_user_home ~/ --skip_configure"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --build_architecture)
                BUILD_ARCHITECTURE="$2"
                shift
                ;;
            --ncpu)
                NCPU="$2"
                shift
                ;;
            --conan_user_home)
                CONAN_USER_HOME="$2"
                shift
                ;;
            --skip_configure)
                SKIP_CONFIGURE=true
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

# Building mount point and environment if $CONAN_USER_HOME not empty
if [[ -n "$CONAN_USER_HOME" ]]; then
    echo "CONAN_USER_HOME: $CONAN_USER_HOME"
    # due safety not mounting user home folder but only .conan
    mkdir -p ${CONAN_USER_HOME}/.conan
    CONAN_USER_HOME_MP="-e CONAN_USER_HOME=$CONAN_USER_HOME -v "${CONAN_USER_HOME}/.conan:${CONAN_USER_HOME}/.conan""
fi

echo "BUILD_ARCHITECTURE: $BUILD_ARCHITECTURE"
echo "NCPU: $NCPU"
echo "SKIP_CONFIGURE: $SKIP_CONFIGURE"

if [ "$SKIP_CONFIGURE" = true ]; then
    echo "Skipping configuration step..."
else
    CONFIGURE="cmake --preset ${BUILD_ARCHITECTURE}-release  && "
fi

docker run --rm -t -u $(id -u):$(id -g) -v ${PWD}:/source \
    ${CONAN_USER_HOME_MP} \
    -w /source/prod/native \
    elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-${BUILD_ARCHITECTURE}-0.0.2 \
    sh -c "${CONFIGURE} cmake --build --preset ${BUILD_ARCHITECTURE}-release -j${NCPU} && ctest --preset ${BUILD_ARCHITECTURE}-release --verbose"
