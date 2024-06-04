#!/bin/bash

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 filename"
    exit 1
fi

FILENAME="$1"

# Multi-line header text to check at the beginning of the file
HEADER=$(cat << 'EOF'
/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */
EOF
)

# Check if the file exists
if [ ! -f "$FILENAME" ]; then
    echo "File $FILENAME does not exist."
    exit 1
fi

FILE_LINES=$(echo "${HEADER}" | wc -l)

# Function to check header from the beginning
check_header_from_beginning() {
    FILE_HEADER=$(head -n "$FILE_LINES" "$FILENAME")
    if [ "${FILE_HEADER}" == "${HEADER}" ]; then
        exit 0
    else
        echo "File ${FILENAME} does NOT start with the specified header."
        exit 1
    fi
}

# Function to check header for PHP files
check_header_for_php() {
    START_LINE=$(grep -n '\/\*' "$FILENAME" | head -n 1 | cut -d: -f1)
    if [ -z "$START_LINE" ]; then
        echo "File ${FILENAME} does not contain a comment starting with /*."
        exit 1
    fi
    FILE_HEADER=$(tail -n +$START_LINE "$FILENAME" | head -n "$FILE_LINES")
    if [ "${FILE_HEADER}" == "${HEADER}" ]; then
        exit 0
    else
        echo "${FILENAME} does NOT contain the specified header at the first comment block."
        exit 1
    fi
}

# Check the file extension and call the appropriate function
EXTENSION="${FILENAME##*.}"
if [ "$EXTENSION" == "php" ]; then
    check_header_for_php
else
    check_header_from_beginning
fi
