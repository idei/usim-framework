#!/bin/bash
set -euo pipefail

# Release helper for packages/idei/usim from monorepo to public repository.
#
# What it does:
# 1) Ensures working tree is clean.
# 2) Creates a subtree split branch for packages/idei/usim.
# 3) Pushes split branch to remote main.
# 4) Optionally creates/pushes a tag.
# 5) Optionally triggers Packagist update.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_color() {
    local message=${1:-}
    local color=${2:-$NC}
    echo -e "${color}${message}${NC}"
}

show_help() {
    cat <<'EOF'
Usage: scripts/release_usim_package [options]

Options:
  -v <version>   Tag version to create and push (example: v0.1.1)
  -r <remote>    Remote name for public package repo (default: usim-public)
  -b <branch>    Source branch in monorepo to release from (default: main)
  -p             Trigger Packagist update API after push (requires env vars)
  -f             Force delete existing local split branch if present
  -h             Show this help

Environment variables for -p:
  PACKAGIST_USERNAME
  PACKAGIST_TOKEN

Examples:
  scripts/release_usim_package
  scripts/release_usim_package -v v0.1.1
  scripts/release_usim_package -v v0.1.1 -p
EOF
}

REMOTE='usim-public'
SOURCE_BRANCH='main'
VERSION=''
TRIGGER_PACKAGIST=false
FORCE=false
PREFIX='packages/idei/usim'
SPLIT_BRANCH='usim-public-release'

while getopts 'v:r:b:pfh' opt; do
    case "$opt" in
        v) VERSION="$OPTARG" ;;
        r) REMOTE="$OPTARG" ;;
        b) SOURCE_BRANCH="$OPTARG" ;;
        p) TRIGGER_PACKAGIST=true ;;
        f) FORCE=true ;;
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

if ! git rev-parse --git-dir >/dev/null 2>&1; then
    print_color 'Error: this must be run inside a git repository.' "$RED"
    exit 1
fi

ROOT_DIR=$(git rev-parse --show-toplevel)
cd "$ROOT_DIR"

if [[ -n "$(git status --porcelain)" ]]; then
    print_color 'Error: working tree is not clean. Commit or stash changes first.' "$RED"
    exit 1
fi

if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    print_color "Error: remote '$REMOTE' does not exist. Configure it first." "$RED"
    exit 1
fi

if ! git show-ref --verify --quiet "refs/heads/$SOURCE_BRANCH"; then
    print_color "Error: source branch '$SOURCE_BRANCH' does not exist locally." "$RED"
    exit 1
fi

if [[ -n "$VERSION" ]] && [[ ! "$VERSION" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.-]+)?$ ]]; then
    print_color "Error: invalid version '$VERSION'. Use semantic versions like v0.1.1" "$RED"
    exit 1
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

cleanup() {
    local code=$?
    if git show-ref --verify --quiet "refs/heads/$SPLIT_BRANCH"; then
        # Keep split branch if script succeeded; remove only on failure to avoid confusion.
        if [[ $code -ne 0 ]]; then
            git branch -D "$SPLIT_BRANCH" >/dev/null 2>&1 || true
        fi
    fi
    if [[ "$CURRENT_BRANCH" != "$(git rev-parse --abbrev-ref HEAD)" ]]; then
        git checkout "$CURRENT_BRANCH" >/dev/null 2>&1 || true
    fi
    exit $code
}
trap cleanup EXIT

print_color "Switching to source branch '$SOURCE_BRANCH'..." "$CYAN"
git checkout "$SOURCE_BRANCH" >/dev/null

print_color 'Validating package metadata...' "$CYAN"
composer validate --strict --no-check-publish --working-dir "$PREFIX" >/dev/null

if git show-ref --verify --quiet "refs/heads/$SPLIT_BRANCH"; then
    if [[ "$FORCE" == true ]]; then
        print_color "Deleting existing split branch '$SPLIT_BRANCH'..." "$YELLOW"
        git branch -D "$SPLIT_BRANCH" >/dev/null
    else
        print_color "Error: split branch '$SPLIT_BRANCH' already exists. Use -f to replace it." "$RED"
        exit 1
    fi
fi

print_color "Creating subtree split branch '$SPLIT_BRANCH'..." "$CYAN"
git subtree split --prefix="$PREFIX" -b "$SPLIT_BRANCH" >/dev/null

print_color "Pushing package branch to '$REMOTE:main'..." "$CYAN"
git push "$REMOTE" "$SPLIT_BRANCH":main

if [[ -n "$VERSION" ]]; then
    print_color "Creating and pushing tag '$VERSION'..." "$CYAN"
    git checkout "$SPLIT_BRANCH" >/dev/null

    if git rev-parse "$VERSION" >/dev/null 2>&1; then
        print_color "Error: tag '$VERSION' already exists locally." "$RED"
        exit 1
    fi

    git tag -a "$VERSION" -m "USIM release $VERSION"
    git push "$REMOTE" "$VERSION"
fi

if [[ "$TRIGGER_PACKAGIST" == true ]]; then
    if [[ -z "${PACKAGIST_USERNAME:-}" || -z "${PACKAGIST_TOKEN:-}" ]]; then
        print_color 'Error: PACKAGIST_USERNAME and PACKAGIST_TOKEN are required for -p.' "$RED"
        exit 1
    fi

    print_color 'Triggering Packagist update...' "$CYAN"
    curl -sS -X POST \
        -H 'Content-Type: application/json' \
        -H "Authorization: Bearer ${PACKAGIST_USERNAME}:${PACKAGIST_TOKEN}" \
        'https://packagist.org/api/update-package' \
        -d '{"repository":"https://github.com/idei/usim.git"}' >/dev/null

    print_color 'Packagist update request sent.' "$GREEN"
fi

print_color 'Release flow completed successfully.' "$GREEN"
