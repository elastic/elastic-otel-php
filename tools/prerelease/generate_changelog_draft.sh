#!/bin/bash

show_help() {
    echo "Usage: $0 --previous-release-tag <tag> [--target <branch_or_tag>]"
    echo
    echo "Options:"
    echo "  --previous-release-tag    The previous release tag (e.g., v0.2.0)."
    echo "  --target                  (Optional) Target branch or tag to compare against (default: main)."
    echo "  --github-token            (Optional) GitHub personal access token."
    echo "  -h, --help                Display this help message."
    exit 1
}

parse_args() {
    TARGET="main"  # Default target branch

    if [[ "$#" -lt 2 ]]; then
        show_help
    fi

    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --previous-release-tag)
                PREVIOUS_TAG="$2"
                shift 2
                ;;
            --target)
                TARGET="$2"
                shift 2
                ;;
            --github-token)
                GITHUB_TOKEN="$2"
                shift 2
                ;;
            -h|--help)
                show_help
                ;;
            *)
                echo "Unknown option: $1"
                show_help
                ;;
        esac
    done

    if [[ -z "$PREVIOUS_TAG" ]]; then
        echo "Error: --previous-release-tag is required."
        show_help
    fi
}

generate_issue_links() {
    local commit_message="$1"

    echo "$commit_message" | sed -E '
    s/\(#([0-9]+)\)/([#\1](https:\/\/github.com\/elastic\/elastic-otel-php\/issues\/\1))/g
    '
}

fetch_pr_for_commit() {
    local commit_hash="$1"
    local pr_response

    local auth_header=""
    if [[ -n "$GITHUB_TOKEN" ]]; then
        auth_header="-H Authorization: Bearer $GITHUB_TOKEN"
    fi

    pr_response=$(curl -s -H "Accept: application/vnd.github+json" $auth_header \
                        "https://api.github.com/repos/elastic/elastic-otel-php/commits/$commit_hash/pulls")

    echo "$pr_response" | jq -r '.[0] | if .html_url then "(PR [#\(.number)](\(.html_url)))" else "" end'
}

generate_changelog() {
    local previous_tag="$1"
    local target_branch_or_tag="$2"

    echo "## v[PUT VERSION TAG HERE]"
    echo

    git log "${previous_tag}..${target_branch_or_tag}" --oneline | while read -r line; do
        # Skip lines matching "github-action*"
        if [[ "$line" =~ github-action ]]; then
            continue
        fi

        # Extract commit hash and message
        commit_hash=$(echo "$line" | awk '{print $1}')
        commit_message=$(echo "$line" | cut -d' ' -f2-)

        commit_message_with_links=$(generate_issue_links "$commit_message")

        pr_link=$(fetch_pr_for_commit "$commit_hash")

        if [[ -n "$pr_link" ]]; then
            commit_message_with_links="$commit_message_with_links $pr_link"
        fi

        echo "- $commit_message_with_links"
    done
}

main() {
    parse_args "$@"
    generate_changelog "$PREVIOUS_TAG" "$TARGET"
}

main "$@"
