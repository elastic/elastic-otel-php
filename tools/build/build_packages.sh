#!/bin/bash

PACKAGE_SHA="unknown"

show_help() {
    echo "Usage: $0 --package_version <version> --build_architecture <architecture> --package_goarchitecture <goarchitecture> [--package_sha <sha>] --package_types <types>"
    echo
    echo "Arguments:"
    echo "  --package_version        Required. Version of the package."
    echo "  --build_architecture     Required. Architecture of the native build. (eg. linux-x86-64)"
    echo "  --package_goarchitecture Required. Architecture of the package in Golang convention. (eg. amd64)"
    echo "  --package_sha            Optional. SHA of the package. Default is fetch from git commit hash or unknown if got doesn't exists."
    echo "  --package_types          Required. List of package types separated by spaces (e.g., 'deb rpm')."
    echo
    echo "Example:"
    echo "  $0 --package_version 1.0.0 --build_architecture linux-x86-64 --package_goarchitecture amd64 --package_types 'deb rpm'"
}

# Function to parse arguments
parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --package_version)
                PACKAGE_VERSION="$2"
                shift
                ;;
            --build_architecture)
                BUILD_ARCHITECUTRE="$2"
                shift
                ;;
            --package_goarchitecture)
                PACKAGE_GOARCHITECTURE="$2"
                shift
                ;;
            --package_sha)
                PACKAGE_SHA="$2"
                shift
                ;;
            --package_types)
                PACKAGE_TYPES=($2)
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

parse_args "$@"

if [[ -z "$PACKAGE_VERSION" ]] || [[ -z "$BUILD_ARCHITECUTRE" ]] || [[ -z "$PACKAGE_GOARCHITECTURE" ]] || [[ -z "$PACKAGE_TYPES" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

test_package() {
    local PHP_VERSION=8.3
    local PKG_TYPE=$1
    local PKG_FILENAME=$2

    echo ${PKG_FILENAME}

    echo "Starting ${PKG_FILENAME} smoke test"

    local TEST_LICENSE_FILES="echo -n 'Checking for \"copyright\" files existence: ' && test -f /opt/elastic/elastic-otel-php/LICENSE && test -f /opt/elastic/elastic-otel-php/NOTICE && echo -e '\033[0;32mOK\033[0;39m'"

    case "${PKG_TYPE}" in
        "apk")
            local INSTALL_SMOKE="apk add --allow-untrusted --verbose --no-cache  /source/build/packages/${PKG_FILENAME} && php /source/packaging/test/smokeTest.php"
            local UNINSTALL_SMOKE="apk del --verbose --no-cache elastic-otel-php && php /source/packaging/test/smokeTestUninstalled.php"
            docker run --rm \
                -v ${PWD}:/source \
                -e ELASTIC_OTEL_LOG_LEVEL_STDERR=error \
                php:${PHP_VERSION}-alpine sh -c "ls /source/build/packages && ${INSTALL_SMOKE} && ${TEST_LICENSE_FILES} && ${UNINSTALL_SMOKE} && ls -alR /opt/elastic"
        ;;
        "deb")
            local INSTALL_SMOKE="dpkg -i  /source/build/packages/${PKG_FILENAME} && php /source/packaging/test/smokeTest.php"
            local UNINSTALL_SMOKE="dpkg --purge elastic-otel-php && php /source/packaging/test/smokeTestUninstalled.php"
            docker run --rm \
                -v ${PWD}:/source \
                -e ELASTIC_OTEL_LOG_LEVEL_STDERR=error \
                php:${PHP_VERSION} sh -c "ls /source/build/packages && ${INSTALL_SMOKE} && ${TEST_LICENSE_FILES} && ${UNINSTALL_SMOKE} && ls -alR /opt/elastic"
        ;;
        "rpm")
            local INSTALL_PHP="cat /etc/redhat-release && dnf install https://dl.fedoraproject.org/pub/epel/epel-release-latest-\$(grep -oP '(?<=release )\d+' /etc/redhat-release).noarch.rpm -y && dnf install https://rpms.remirepo.net/enterprise/remi-release-\$(grep -oP '(?<=release )\d+\.\d+' /etc/redhat-release).rpm -y && dnf install --setopt=install_weak_deps=False -y php${PHP_VERSION//./} php${PHP_VERSION//./}-syspaths"
            local INSTALL_SMOKE="rpm -ivh /source/build/packages/${PKG_FILENAME} && php /source/packaging/test/smokeTest.php"
            local UNINSTALL_SMOKE="rpm -ve elastic-otel-php && php /source/packaging/test/smokeTestUninstalled.php"

            docker run --rm \
                -v ${PWD}:/source \
                -e ELASTIC_OTEL_LOG_LEVEL_STDERR=error \
                redhat/ubi9 sh -c "ls /source/build/packages && ${INSTALL_PHP} && ${INSTALL_SMOKE} && ${TEST_LICENSE_FILES} && ${UNINSTALL_SMOKE} && ls -alR /opt/elastic"
        ;;
        *)
            echo -e "\033[0;33mPackage ${PKG_FILENAME} can't be tested because smoke test is not implemented\033[0;39m"
        ;;
    esac

    if [ $? -ne 0 ]; then
        echo -e "\033[0;31mPackage ${PKG_FILENAME} smoke test FAILED\033[0;39m"
        exit 1
    fi

}

if [ "${PACKAGE_SHA}" == "unknown" ]; then
    GITCMD=$(command -v git)
    if [ $? -eq 0 ]; then
        PACKAGE_SHA=$( ${GITCMD} rev-parse HEAD )
    fi
fi

echo "PACKAGE_VERSION: $PACKAGE_VERSION"
echo "BUILD_ARCHITECUTRE: $BUILD_ARCHITECUTRE"
echo "PACKAGE_GOARCHITECTURE: $PACKAGE_GOARCHITECTURE"
echo "PACKAGE_SHA: $PACKAGE_SHA"
echo "PACKAGE_TYPES: $PACKAGE_TYPES"

export PACKAGE_VERSION="${PACKAGE_VERSION}"
export BUILD_ARCHITECUTRE="${BUILD_ARCHITECUTRE}"
export PACKAGE_GOARCHITECTURE="${PACKAGE_GOARCHITECTURE}"
export PACKAGE_SHA="${PACKAGE_SHA}"

mkdir -p "${PWD}/build/packages"
envsubst <packaging/nfpm.yaml >${PWD}/build/packages/nfpm.yaml

for pkg in "${PACKAGE_TYPES[@]}"
do
    echo "Building package type: ${PKG}"

    docker run --rm \
        -e PACKAGE_VERSION="${PACKAGE_VERSION}" \
        -e BUILD_ARCHITECUTRE="${BUILD_ARCHITECUTRE}" \
        -e PACKAGE_GOARCHITECTURE="${PACKAGE_GOARCHITECTURE}" \
        -e PACKAGE_SHA="${PACKAGE_SHA}" \
        -v ${PWD}:/source \
        -w /source/packaging goreleaser/nfpm package -f /source/build/packages/nfpm.yaml -t "/source/build/packages" -p ${pkg} | tee /tmp/nfpm_output.txt

    PKG_FILENAME=$(grep "created package: " /tmp/nfpm_output.txt | sed 's/^.*: \/source\/build\/packages\///')

    if [ -z "${PKG_FILENAME}" ]; then
        echo "Error creating package"
        exit 1
    fi

    # create sha512 file
    pushd "${PWD}/build/packages"
    md5sum "${PKG_FILENAME}" >"${PKG_FILENAME}".sha512
    popd

    test_package ${pkg} "${PKG_FILENAME}"

done

rm ${PWD}/build/packages/nfpm.yaml

echo "Creating debug symbols artifacts"
DBGSYM="${PWD}/build/packages/elastic-otel-php-debugsymbols-${BUILD_ARCHITECUTRE}.tar.gz"
pushd prod/native/_build/${BUILD_ARCHITECUTRE}-release
tar --transform 's/.*\///g' -zcvf ${DBGSYM} extension/code/*.debug loader/code/*.debug
popd

pushd "${PWD}/build/packages"
md5sum ${DBGSYM} >${DBGSYM}.sha512
popd