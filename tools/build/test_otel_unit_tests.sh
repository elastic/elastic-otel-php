#!/bin/bash

OPT_WORKINGDIR=/tmp/test-run
OPT_REPORTS_DESTINATION_PATH=/tmp/reports

show_help() {
    cat <<EOF
Usage: $0 -f <path_to_composer.json> -p <pattern> [-p <pattern> ...]
  -f   Path to the composer.json file
  -p   Package name pattern (can be specified multiple times)
  -w   Working directory (default '/tmp/test-run')
  -r   Destination path for junit reports (default '/tmp/reports')
  -q   Optional. Composer in quiet mode.
  -h   Display this help message
EOF
}

export ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED_CLI=false
export ELASTIC_OTEL_TRANSACTION_SPAN_ENABLED=false

OPT_COMPOSER_FILE=""
OPT_PATTERNS=()

# Parse options using getopts
while getopts "f:r:p:w:rhq" opt; do
    case "$opt" in
    f)
        OPT_COMPOSER_FILE="$OPTARG"
        ;;
    r)
        OPT_REPORTS_DESTINATION_PATH="$OPTARG"
        ;;
    p)
        OPT_PATTERNS+=("$OPTARG")
        ;;
    w)
        OPT_WORKINGDIR="$OPTARG"
        ;;
    q)
        OPT_QUIET=" --quiet "
        ;;
    h)
        show_help
        exit 0
        ;;
    *)
        show_help
        exit 1
        ;;
    esac
done

if [[ -z "$OPT_COMPOSER_FILE" || ${#OPT_PATTERNS[@]} -eq 0 ]]; then
    echo "::error::You must provide a composer.json file path and at least one pattern." >&2
    show_help
    exit 1
fi

if [[ ! -f "$OPT_COMPOSER_FILE" ]]; then
    echo "::error::File '$OPT_COMPOSER_FILE' does not exist." >&2
    exit 1
fi

# Verify that jq is installed
if ! command -v jq &>/dev/null; then
    echo "::error::'jq' is not installed. Please install it and try again." >&2
    exit 1
fi

array_contains_element() {
    local element
    for element in "${@:2}"; do
        [[ "$element" == "$1" ]] && return 0
    done
    return 1
}

match_packages() {
    MATCHED_PACKAGES=()
    local pattern
    local local_found
    for pattern in "${OPT_PATTERNS[@]}"; do
        echo "Searching for packages matching pattern: $pattern"
        local_found=0
        while IFS= read -r pkg; do
            # Use Bash pattern matching (wildcards) to compare package names
            if [[ "$pkg" == $pattern ]]; then
                echo "  Matched: $pkg"
                local_found=1

                if ! array_contains_element "$pkg" "${MATCHED_PACKAGES[@]}"; then
                    MATCHED_PACKAGES+=("$pkg")
                fi
            fi
        done <<<"$REQUIRE_PACKAGES"
        if [[ $local_found -eq 0 ]]; then
            echo "  No packages match pattern: $pattern"
        fi
    done
}

setup_composer_project() {
    echo "::group::Installing composer project"
    composer ${OPT_QUIET} init -n --name "elastic/otel-tests"
    composer ${OPT_QUIET} config --no-plugins allow-plugins.php-http/discovery false
    composer ${OPT_QUIET} config allow-plugins.tbachert/spi false
    local package
    for package in "${MATCHED_PACKAGES[@]}"; do
        composer ${OPT_QUIET} config preferred-install.$package source
    done
    composer ${OPT_QUIET} config preferred-install.* dist
    composer ${OPT_QUIET} update
    echo "::endgroup::"
}

function build_list_of_packages_with_version_to_install { # Build a list of packages with version constraints to install at once
    LIST_OF_PACKAGES_TO_INSTALL=()
    local package
    local version

    for package in "${MATCHED_PACKAGES[@]}"; do
        # Extract the version constraint from the composer.json file
        version=$(jq -r --arg pkg "$package" '.require[$pkg]' "$OPT_COMPOSER_FILE")
        if [[ -z "$version" || "$version" == "null" ]]; then
            echo "::error::No version constraint found for package $package." >&2
            continue
        fi
        LIST_OF_PACKAGES_TO_INSTALL+=("$package:$version")
    done
}

# Extract package names from the "require" section using jq
REQUIRE_PACKAGES=$(jq -r '.require | keys[]' "$OPT_COMPOSER_FILE")

if [[ -z "$REQUIRE_PACKAGES" ]]; then
    echo "No packages found in the 'require' section of $OPT_COMPOSER_FILE."
    exit 0
fi

match_packages

if [[ ${#MATCHED_PACKAGES[@]} -eq 0 ]]; then
    echo "::error::No packages matched the provided OPT_PATTERNS."
    exit 1
fi

build_list_of_packages_with_version_to_install

if [[ ${#LIST_OF_PACKAGES_TO_INSTALL[@]} -eq 0 ]]; then
    echo "::error::No valid packages with version constraints found."
    exit 1
fi

mkdir -p "${OPT_REPORTS_DESTINATION_PATH}"
mkdir -p "${OPT_WORKINGDIR}"

cd "${OPT_WORKINGDIR}"

setup_composer_project

echo "::group::Installing matched packages with specified versions:"
echo "  ${LIST_OF_PACKAGES_TO_INSTALL[*]}"

composer ${OPT_QUIET} require --dev --ignore-platform-req php "${LIST_OF_PACKAGES_TO_INSTALL[@]}"
if [[ $? -ne 0 ]]; then
    echo "::error::Failed to install one or more packages"
    echo "::endgroup::"
    popd
    exit 1
fi

cd -

echo "::endgroup::"

FAILURE=false

for package in "${MATCHED_PACKAGES[@]}"; do
    vendor_dir="${OPT_WORKINGDIR}/vendor/$package"

    echo "Preparing PHPUnit tests for package in directory: $vendor_dir"

    cd $vendor_dir
    echo "::group::Installing $package dependencies"
    composer ${OPT_QUIET} config --no-plugins allow-plugins.php-http/discovery false
    composer ${OPT_QUIET} config allow-plugins.tbachert/spi true
    composer ${OPT_QUIET} install --dev --ignore-platform-req php
    echo "::endgroup::"

    echo "::group::🚀 Running $package tests 🚀"
    ./vendor/bin/phpunit --debug --log-junit ${OPT_REPORTS_DESTINATION_PATH}/$package.xml

    if [[ $? -ne 0 ]]; then
        echo "::error::PHPUnit tests failed for package $package"
        FAILURE=true
    fi

    echo "::endgroup::"
    cd -

done

if [ "$FAILURE" = "true" ]; then
    echo "::error::At least one test failed"
    exit 1
fi
