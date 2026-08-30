#!/usr/bin/env bash
set -euo pipefail

# Rewrites the plugin version number in every place it's duplicated, so a
# release tag, the plugin header, and readme.txt can't drift out of sync.
# Usage: bin/bump-version.sh 0.4.9

if [[ $# -ne 1 ]]; then
  echo "Usage: bin/bump-version.sh <new-version>" >&2
  exit 1
fi

NEW_VERSION="$1"

if [[ ! "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Error: version must look like 1.2.3 (got '$NEW_VERSION')" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PACKAGE_JSON="package.json"
PLUGIN_FILE="custom-icons-for-divi.php"
README_FILE="readme.txt"

update_or_fail() {
  local file="$1"
  local pattern="$2"
  local replacement="$3"
  local label="$4"

  if ! grep -qE "$pattern" "$file"; then
    echo "Error: could not find $label in $file" >&2
    exit 1
  fi

  sed -i '' -E "s/$pattern/$replacement/" "$file"
}

update_or_fail "$PACKAGE_JSON" '"version": "[0-9]+\.[0-9]+\.[0-9]+"' "\"version\": \"$NEW_VERSION\"" '"version"'
update_or_fail "$PLUGIN_FILE" '\* Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+' " * Version: $NEW_VERSION" 'Version: header'
update_or_fail "$README_FILE" 'Stable tag: [0-9]+\.[0-9]+\.[0-9]+' "Stable tag: $NEW_VERSION" 'Stable tag:'

echo "Bumped version to $NEW_VERSION in:"
echo "  - $PACKAGE_JSON"
echo "  - $PLUGIN_FILE"
echo "  - $README_FILE"
echo ""
echo "Next: update the readme.txt Changelog and CHANGELOG.md entries, commit,"
echo "then: git tag v$NEW_VERSION && git push --tags"
