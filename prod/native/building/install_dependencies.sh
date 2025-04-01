#!/bin/bash

SCRIPT_DIR=$(dirname "$(realpath "${BASH_SOURCE[0]}")")

show_help() {
    echo "Usage: $0 --build_output_path <path> --build_type <type>"
    echo
    echo "Arguments:"
    echo "  --build_output_path      Required. Specifies the build folder path (e.g., _build)."
    echo "  --build_preset           Optional. Specifies the build profile name."
    echo "                           The script will install and use a Conan profile from the conan/profiles folder (e.g., 'linux-x86-64-release')."
    echo "                           If not specified, the default profile will be used."
    echo "  --build_type             Required if --build_preset is not specified. Overrides the build type specified in the preset if both are provided."
    echo "                           Build type: Release or Debug."
    echo "  --detect_conan_profile   Optional. Detects a Conan profile if running Conan for the first time."
    echo "                           Only works if --build_preset is not specified."
    echo "  --skip_venv_conan        Optional. Skips the creation of a virtual environment and Conan installation."
    echo "  --force_install          Optional. Forces Conan installation in an existing virtual environment."
    echo "  --trace                  Optional. Enables trace output."
    echo
    echo "Example:"
    echo "  $0 --build_output_path _build/linux-x86-64-release --build_preset linux-x86-64-release --build_type Release"
}

parse_args() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --build_output_path)
                ARG_BUILD_OUTPUT_PATH="$2"
                shift
                ;;
            --build_preset)
                ARG_BUILD_PRESET="$2"
                shift
                ;;
            --build_type)
                ARG_BUILD_TYPE="$2"
                shift
                ;;
            --detect_conan_profile)
                ARG_DETECT_CONAN_PROFILE=true
                shift
                ;;
            --skip_venv_conan)
                ARG_SKIP_VENV_CONAN=true
                shift
                ;;
            --force_install)
                ARG_FORCE_INSTALL="--force_install"
                shift
                ;;
            --trace)
                ARG_TRACE=" -vvv "
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

if [[ -z "$ARG_BUILD_OUTPUT_PATH" ]]; then
    echo "Error: Missing required arguments."
    show_help
    exit 1
fi

if [[ -n "${ARG_BUILD_TYPE}" ]]; then
    if [[ "$ARG_BUILD_TYPE" != "Release" && "$ARG_BUILD_TYPE" != "Debug" ]]; then
        echo "Error: --build_type must be 'Release' or 'Debug'."
        show_help
        exit 1
    fi
    OPTION_BUILD_TYPE=" -s build_type=${ARG_BUILD_TYPE} "
fi

ARG_BUILD_TYPE_LC=${ARG_BUILD_TYPE,,}

if [[ -n "${ARG_SKIP_VENV_CONAN}" ]]; then
    echo "Skipping venv and conan installation"
else
    source ${SCRIPT_DIR}/install_venv_conan.sh --build_path "${ARG_BUILD_OUTPUT_PATH}/" ${ARG_FORCE_INSTALL}
fi



# installing profile only for known arch
if [[ -n "${ARG_BUILD_PRESET}" ]]; then
    echo "Installing conan profiles and settings for ${ARG_BUILD_PRESET}"

    conan config install "${SCRIPT_DIR}/conan/profiles/${ARG_BUILD_PRESET}"  -tf profiles
    conan config install "${SCRIPT_DIR}/conan/settings.yml"

    OPTION_PROFILE=" --profile:build=${ARG_BUILD_PRESET} --profile:host=${ARG_BUILD_PRESET} "
else

    if [[ -n "${ARG_DETECT_CONAN_PROFILE}" ]]; then
       conan profile detect
    fi
fi

conan remote add -vquiet --index 0 ElasticConan https://artifactory.elastic.dev/artifactory/api/conan/apm-agent-php-dev
conan remote update --secure --index 1 --url https://center.conan.io conancenter

source ${SCRIPT_DIR}/../../../tools/read_properties.sh
read_properties ${SCRIPT_DIR}/../../../elastic-otel-php.properties PROJECT_PROPERTIES
PHP_VERSIONS=(${PROJECT_PROPERTIES_SUPPORTED_PHP_VERSIONS//[()]/})

for PHP_VERSION in "${PHP_VERSIONS[@]}"
do
    CREATE_REFERENCE=php-headers-${PHP_VERSION}
    echo "Searching for ${CREATE_REFERENCE}/${PROJECT_PROPERTIES_PHP_HEADERS_VERSION} in local cache/remotes"

    if conan list -c ${CREATE_REFERENCE}/${PROJECT_PROPERTIES_PHP_HEADERS_VERSION}:* -fs="build_type=${ARG_BUILD_TYPE}" 2>&1 | grep -q "not found"; then
        if conan search -f json ${CREATE_REFERENCE}/${PROJECT_PROPERTIES_PHP_HEADERS_VERSION} 2>&1 | grep -q "Found"; then
            echo "Package php-headers-${PHP_VERSION} found in remote"
        else
            echo "Package php-headers-${PHP_VERSION} not found - creating"
            conan create --build=missing ${ARG_TRACE} ${OPTION_PROFILE} ${OPTION_BUILD_TYPE} --name ${CREATE_REFERENCE} ${SCRIPT_DIR}/dependencies/php-headers
        fi

    fi

done

 # conan will create build/${OPTION_BUILD_TYPE}/generators fordlers inside ${ARG_BUILD_OUTPUT_PATH}

conan install ${ARG_TRACE} --build=missing ${OPTION_PROFILE} ${OPTION_BUILD_TYPE} -of ${ARG_BUILD_OUTPUT_PATH} ${SCRIPT_DIR}/../conanfile.txt
