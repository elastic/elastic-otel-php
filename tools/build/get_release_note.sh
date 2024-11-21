#!/bin/bash

CHANGELOG_FILE="CHANGELOG.md"

show_help() {
    echo "Usage: $0 --release-tag <tag> [--changelog-file <file>]"
    echo
    echo "Options:"
    echo "  --release-tag        The current release tag (e.g., v0.2.0)."
    echo "  --changelog-file     Path to the changelog file (default: CHANGELOG.md)."
    echo "  -h, --help           Display this help message."
}

parse_arguments() {
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --release-tag)
                RELEASE_TAG="$2"
                shift 2
                ;;
            --changelog-file)
                CHANGELOG_FILE="$2"
                shift 2
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            *)
                echo "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done

    if [[ -z "$RELEASE_TAG" ]]; then
        echo "Error: --release-tag is required."
        show_help
        exit 1
    fi
}

find_previous_release_tag() {
    local release_tag="$1"
    local changelog_file="$2"

    grep "^## v" "$changelog_file" | awk '{print $2}' | sort -rV | grep -A 1 "^$release_tag" | tail -n 1
}

extract_release_notes() {
    local release_tag="$1"
    local previous_tag="$2"
    local changelog_file="$3"

    awk -v tag="$release_tag" -v prev_tag="$previous_tag" '
    BEGIN { found = 0 }
    /^## / {
        if ($2 == tag) { found = 1; next }
        if (found && $2 == prev_tag) { exit }
    }
    found { print }
    ' "$changelog_file"
}

main() {
    parse_arguments "$@"

    if [[ ! -f "$CHANGELOG_FILE" ]]; then
        echo "Error: Changelog file '$CHANGELOG_FILE' not found."
        exit 1
    fi

    PREVIOUS_TAG=$(find_previous_release_tag "$RELEASE_TAG" "$CHANGELOG_FILE")

    if [[ -z "$PREVIOUS_TAG" ]]; then
        # echo "Error: Could not find the previous tag for release '$RELEASE_TAG' in '$CHANGELOG_FILE'."
        # exit 1
        exit 0
    fi

    RELEASE_NOTES=$(extract_release_notes "$RELEASE_TAG" "$PREVIOUS_TAG" "$CHANGELOG_FILE")

    if [[ -z "$RELEASE_NOTES" ]]; then
        # echo "Error: No release notes found for tag '$RELEASE_TAG'."
        # exit 1
        exit 0
    fi

    echo "$RELEASE_NOTES"

    if [ ${PREVIOUS_TAG} != ${RELEASE_TAG} ]; then
        echo -e "\nFull changelog: [${PREVIOUS_TAG}...${RELEASE_TAG}](https://github.com/elastic/elastic-otel-php/compare/${PREVIOUS_TAG}...${RELEASE_TAG})"
    fi
}

main "$@"
