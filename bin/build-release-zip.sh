#!/usr/bin/env bash
set -euo pipefail

# Builds a clean, installable plugin ZIP from a fresh checkout of the current
# working tree. Excludes development-only files (tests, source TypeScript,
# node_modules, docs, skills, CI config) so the archive contains only what a
# WordPress site needs to run the plugin.
#
# The staged directory name is the plugin slug and must match the existing
# folder name (custom-icons-for-divi) so activation state and option data on
# existing installs are not disrupted by a cosmetic rebrand. See
# docs/audits/2026-08-30-security-release-readiness.md.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="custom-icons-for-divi"
VERSION="$(node -p "require('./package.json').version")"
BUILD_DIR="$ROOT_DIR/build"
STAGE_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ZIP_PATH="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "==> Verifying tests pass before packaging"
composer test
npm test
npm run build

echo "==> Staging release contents in $STAGE_DIR"
rm -rf "$BUILD_DIR"
mkdir -p "$STAGE_DIR"

rsync -a \
  --exclude-from="$ROOT_DIR/.distignore" \
  "$ROOT_DIR/" "$STAGE_DIR/" \
  --exclude "build/"

echo "==> Creating $ZIP_PATH"
(cd "$BUILD_DIR" && zip -r -X -q "$(basename "$ZIP_PATH")" "$PLUGIN_SLUG")

echo "==> Done"
echo "Archive: $ZIP_PATH"
echo "Contents:"
unzip -l "$ZIP_PATH"
