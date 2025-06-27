#!/bin/bash

read_properties() {
    local file="$1"
    local prefix="$2"

    if [[ ! -f "$file" ]]; then
        echo "File not found: $file"
        return 1
    fi

    while IFS='=' read -r key value; do
        key=$(echo $key | sed 's/^[ \t]*//;s/[ \t]*$//')
        value=$(echo $value | sed 's/^[ \t]*//;s/[ \t]*$//')

        if [[ -z "$key" || "$key" =~ ^# ]]; then
            continue
        fi

        if [[ -n "$key" && -n "$value" ]]; then
            local var_name="${prefix}_$(echo $key | tr '[:lower:]' '[:upper:]')"
            export "$var_name"="$value"
        fi
    done <"$file"
}
