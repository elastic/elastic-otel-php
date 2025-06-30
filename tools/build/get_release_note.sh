#!/bin/bash

CHANGELOG_FILE="docs/release-notes/index.md"

show_help() {
    echo "Usage: $0 --release-tag <tag> [--changelog-file <file>]"
    echo
    echo "Options:"
    echo "  --release-tag        The current release tag (e.g., 1.0.0)."
    echo "  --changelog-file     Path to the changelog file (default: docs/release-notes/index.md)."
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

    # Remove 'v' prefix if present for compatibility
    RELEASE_TAG="${RELEASE_TAG#v}"
}

find_previous_release_tag() {
    local release_tag="$1"
    local changelog_file="$2"

    # Extract version numbers, ignoring the anchor tags
    grep -E "^## [0-9]+\.[0-9]+\.[0-9]+" "$changelog_file" | sed 's/^## \([0-9]\+\.[0-9]\+\.[0-9]\+\).*/\1/' | sort -rV | grep -A 1 "^$release_tag" | tail -n 1
}

extract_release_notes() {
    local release_tag="$1"
    local changelog_file="$2"
    local release_section=""
    local in_section=0
    local next_section_found=0

    while IFS= read -r line; do
        # Check if we've found a new version section with anchor
        if [[ "$line" =~ ^##\ ([0-9]+\.[0-9]+\.[0-9]+) ]]; then
            current_version="${BASH_REMATCH[1]}"
            
            # If we found our target version
            if [[ "$current_version" == "$release_tag" ]]; then
                in_section=1
                # Include the full heading with anchor tag
                release_section+="$line\n"
                continue
            # If we were in our target section and found the next version
            elif [[ $in_section -eq 1 ]]; then
                next_section_found=1
                break
            fi
        fi
        
        # If we're in the target section, add the line to our collection
        if [[ $in_section -eq 1 ]]; then
            release_section+="$line\n"
        fi
    done < "$changelog_file"

    # If we never found our section, return empty
    if [[ $in_section -eq 0 ]]; then
        echo ""
        return
    fi
    
    # Remove trailing newline
    release_section=${release_section%\\n}
    echo -e "$release_section"
}

main() {
    parse_arguments "$@"

    if [[ ! -f "$CHANGELOG_FILE" ]]; then
        echo "Error: Changelog file '$CHANGELOG_FILE' not found."
        exit 1
    fi

    PREVIOUS_TAG=$(find_previous_release_tag "$RELEASE_TAG" "$CHANGELOG_FILE")

    RELEASE_NOTES=$(extract_release_notes "$RELEASE_TAG" "$CHANGELOG_FILE")

    if [[ -z "$RELEASE_NOTES" ]]; then
        # echo "Error: No release notes found for tag '$RELEASE_TAG'."
        # exit 1
        exit 0
    fi

    echo "$RELEASE_NOTES"

    if [ "${PREVIOUS_TAG}" != "${RELEASE_TAG}" ] && [ ! -z "${PREVIOUS_TAG}" ]; then
        # For GitHub links, prepend 'v' if not present
        GITHUB_PREVIOUS_TAG="${PREVIOUS_TAG}"
        GITHUB_RELEASE_TAG="${RELEASE_TAG}"
        if [[ ! "$GITHUB_PREVIOUS_TAG" =~ ^v ]]; then
            GITHUB_PREVIOUS_TAG="v$GITHUB_PREVIOUS_TAG"
        fi
        if [[ ! "$GITHUB_RELEASE_TAG" =~ ^v ]]; then
            GITHUB_RELEASE_TAG="v$GITHUB_RELEASE_TAG"
        fi
        echo -e "\nFull changelog: [${GITHUB_PREVIOUS_TAG}...${GITHUB_RELEASE_TAG}](https://github.com/elastic/elastic-otel-php/compare/${GITHUB_PREVIOUS_TAG}...${GITHUB_RELEASE_TAG})"
    fi
}

main "$@"
