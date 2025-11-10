#!/bin/bash
#
# This script generates PHP files from template files.
# The data to populate the templates is retrieved from the elastic-otel-php.properties file
# using the read_properties.sh script, which processes the properties and stores them
# in environment variables with the _PROJECT_PROPERTIES prefix.
# In the template file, each text of the form @_PROJECT_PROPERTIES_SOME_VARIABLE@
# will be replaced with the content of the environment variable _PROJECT_PROPERTIES_SOME_VARIABLE

set -o pipefail
set -e
set -u

source ./tools/read_properties.sh

read_properties elastic-otel-php.properties _PROJECT_PROPERTIES

# The function only works if the environment variable GITHUB_SHA is not set
# It retrieves the hash of the current commit, and appends '-dirty' if there are local changes
# Arguments:
#   none
# Returns:
#   The git hash string will be printed to stdout
get_git_hash() {
    if [ -z "${GITHUB_SHA+x}" ]; then
        IS_DIRTY=false

        git diff-index --quiet HEAD --
        GIT_RESULT=$?

        if [ $GIT_RESULT -ne 0 ]; then
            IS_DIRTY=true
        fi

        GIT_VERSION=$(git rev-parse --short HEAD 2>/dev/null)
        GIT_RESULT=$?

        if [ $GIT_RESULT -ne 0 ]; then
            TMP_OUTPUT_HASH=""
        else
            if [ "$IS_DIRTY" = true ]; then
                TMP_OUTPUT_HASH="~${GIT_VERSION}-dirty"
            else
                TMP_OUTPUT_HASH="~${GIT_VERSION}"
            fi
        fi

        echo "$TMP_OUTPUT_HASH"
    fi
}

# Transform comma-separated values into a PHP 8.0 compatible "enum" class
# Arguments:
#   1 - input values like "VAL=0,VAL2=0,VAL3=0"
# Returns:
#   PHP-style const class members will be printed to stdout
transform_comma_separated_values_into_php_const_members() {
    local _ARG_INPUT_VALUES=$1

    IFS="," read -ra _ARG_INPUT_VALUES_ARRAY <<< ${_ARG_INPUT_VALUES}
    for _VAL in "${_ARG_INPUT_VALUES_ARRAY[@]}"; do
        echo "    public const ${_VAL};"
    done
}

# Replaces placeholders of the form @VAR@ with the values of the corresponding environment variables VAR
# Arguments:
#   1 - input file name (template)
# Returns:
#   The content of the file after replacement will be printed to stdout
function configure_file() {
    local _ARG_INPUT_FILE=$1

    while IFS= read -r line; do
        while [[ "$line" =~ @(.*?)@ ]]; do
            var_name="${BASH_REMATCH[1]}"  # find var name
            var_value="${!var_name}"       # get var value
            line="${line//@${var_name}@/${var_value}}"  # replace @VAR@ with value
        done
        echo "$line"
    done < ${_ARG_INPUT_FILE}
}

# Replaces placeholders of the form @VAR@ with the values of the corresponding environment variables VAR
# Arguments:
#   1 - input filename ending with *.template
# Returns:
#   The resulting file with the .template extension removed
function configure_from_template() {
    local _ARG_INPUT_FILE=$1

    if [[ "${_ARG_INPUT_FILE}" != *.template ]]; then
        echo "configure_from_template error: File name must end with '.template'" >&2
        exit 1
    fi

    local _OUTPUT_FILE=${_ARG_INPUT_FILE%.template}

    echo "Configuring file ${_OUTPUT_FILE} from ${_ARG_INPUT_FILE}"

    configure_file "${_ARG_INPUT_FILE}" >"${_OUTPUT_FILE}"
}

# Transform values read from properties before templating

_PROJECT_PROPERTIES_LOGGER_FEATURES_ENUM_VALUES=$(transform_comma_separated_values_into_php_const_members "${_PROJECT_PROPERTIES_LOGGER_FEATURES_ENUM_VALUES}")
_PROJECT_PROPERTIES_VERSION="${_PROJECT_PROPERTIES_VERSION}$(get_git_hash)"

configure_from_template "prod/php/ElasticOTel/Log/LogFeature.php.template"
configure_from_template "prod/php/ElasticOTel/PhpPartVersion.php.template"
