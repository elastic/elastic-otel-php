#!/bin/bash

source ./tools/read_properties.sh

read_properties elastic-otel-php.properties ELASTIC_OTEL_PHP

get_git_hash() {
    if [ -z "${GITHUB_SHA}" ]; then
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

ELASTIC_OTEL_PHP_VERSION="${ELASTIC_OTEL_PHP_VERSION}$(get_git_hash)"

sed "s/__ELASTIC_OTEL_PHP_VERSION__/$ELASTIC_OTEL_PHP_VERSION/g" prod/php/ElasticOTel/PhpPartVersion.php.template > prod/php/ElasticOTel/PhpPartVersion.php

echo "The file prod/php/ElasticOTel/PhpPartVersion.php has been generated with version ${ELASTIC_OTEL_PHP_VERSION}"