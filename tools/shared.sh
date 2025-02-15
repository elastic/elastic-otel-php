#!/usr/bin/env bash
set -e -o pipefail
#set -x

source "${repo_root_dir:?}/elastic-otel-php.properties"

get_lowest_supported_php_version() {
    local min_supported_php_version=${supported_php_versions[0]:?}
    for current_supported_php_version in "${supported_php_versions[@]:?}" ; do
        ((current_supported_php_version < min_supported_php_version)) && min_supported_php_version=${current_supported_php_version}
    done
    echo "${min_supported_php_version}"
}

get_highest_supported_php_version() {
    local max_supported_php_version=${supported_php_versions[0]:?}
    for current_supported_php_version in "${supported_php_versions[@]:?}" ; do
        ((current_supported_php_version > max_supported_php_version)) && max_supported_php_version=${current_supported_php_version}
    done
    echo "${max_supported_php_version}"
}

convert_no_dot_to_dot_separated_version() {
    local no_dot_version=${1:?}
    local no_dot_version_str_len=${#no_dot_version}

    if [ "${no_dot_version_str_len}" -ne 2 ]; then
        echo "Dot version should have length 2"
        exit 1
    fi

    echo "${no_dot_version:0:1}.${no_dot_version:1:1}"
}

convert_dot_separated_to_no_dot_version() {
    local dot_separated_version=${1:?}
    echo "${dot_separated_version/\./}"
}

adapt_architecture_to_package_type() {
    # architecture must be either arm64 or x86_64 regardless of package_type
    local architecture=${1:?}
    local package_type=${2:?}

    local adapted_arm64
    local adapted_x86_64
    case "${package_type}" in
        apk)
            # Example for package file names:
            #    elastic-otel-php_0.3.0_aarch64.apk
            #    elastic-otel-php_0.3.0_x86_64.apk
            adapted_arm64=aarch64
            adapted_x86_64=x86_64
            ;;
        deb)
            # Example for package file names:
            #    elastic-otel-php_0.3.0_amd64.deb
            #    elastic-otel-php_0.3.0_arm64.deb
            adapted_arm64=arm64
            adapted_x86_64=amd64
            ;;
        rpm)
            # Example for package file names:
            #    elastic-otel-php-0.3.0-1.aarch64.rpm
            #    elastic-otel-php-0.3.0-1.x86_64.rpm
            adapted_arm64=aarch64
            adapted_x86_64=x86_64
            ;;
        *)
            echo "Unknown package type: ${package_type}"
            exit 1
            ;;
    esac

    case "${architecture}" in
        arm64)
            echo "${adapted_arm64}"
            ;;
        x86_64)
            echo "${adapted_x86_64}"
            ;;
        *)
            echo "Unknown architecture: ${architecture}"
            exit 1
            ;;
    esac
}

select_elastic_otel_package_file() {
    local packages_dir=${1:?}
    local package_type=${2:?}
    # architecture must be either arm64 or x86_64 regardless of package_type
    local architecture=${3:?}

    # Example for package file names:
    #    elastic-otel-php-0.3.0-1.aarch64.rpm
    #    elastic-otel-php-0.3.0-1.x86_64.rpm
    #    elastic-otel-php_0.3.0_aarch64.apk
    #    elastic-otel-php_0.3.0_amd64.deb
    #    elastic-otel-php_0.3.0_arm64.deb
    #    elastic-otel-php_0.3.0_x86_64.apk

    local architecture_adapted_to_package_type
    architecture_adapted_to_package_type=$(adapt_architecture_to_package_type "${architecture}")

    local found_files
    local found_files_count=0
    for current_file in "${packages_dir}"/*"${architecture_adapted_to_package_type}.${package_type}"; do
        # -n is true if string is not empty
        if [[ -n "${found_files}" ]]; then
            found_files="${found_files} ${current_file}"
        else
            found_files="${current_file}"
        fi
        ((++found_files_count))
    done

    if [ "${found_files_count}" -ne 1 ]; then
        echo "Number of found files should be 1, found_files_count: ${found_files_count}, found_files: ${found_files}"
        exit 1
    fi

    echo "${found_files}"
}

install_package_file() {
    local package_file_full_path=${1:?}

    local package_file_name_with_ext
    package_file_name_with_ext=$(basename "${package_file_full_path}")
    local package_file_extension
    package_file_extension="${package_file_name_with_ext##*.}"

    case "${package_file_extension}" in
        apk)
            apk add --allow-untrusted "${package_file_full_path}"
            ;;
        deb)
            dpkg -i "${package_file_full_path}"
            ;;
        rpm)
            rpm -ivh "${package_file_full_path}"
            ;;
        *)
            echo "Unknown package type: ${package_type}"
            exit 1
            ;;
    esac
}
