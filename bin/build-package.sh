#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="crm-leads-capture"
MAIN_FILE="${ROOT_DIR}/${PLUGIN_SLUG}.php"

VERSION="$(sed -nE 's/^[[:space:]*]*Version:[[:space:]]*([^[:space:]].*)$/\1/ip' "${MAIN_FILE}" | head -n 1 | tr -d '\r')"

if [[ -z "${VERSION}" ]]; then
  echo "Could not detect plugin version from ${MAIN_FILE}." >&2
  exit 1
fi

BUILD_DIR="${ROOT_DIR}/build/package"
PACKAGE_ROOT="${BUILD_DIR}/${PLUGIN_SLUG}"
DIST_DIR="${ROOT_DIR}/dist"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "${BUILD_DIR}"
mkdir -p "${PACKAGE_ROOT}" "${DIST_DIR}"

rsync -a "${ROOT_DIR}/" "${PACKAGE_ROOT}/" \
  --exclude ".git/" \
  --exclude ".github/" \
  --exclude ".gitignore" \
  --exclude ".DS_Store" \
  --exclude ".env" \
  --exclude ".env.*" \
  --exclude ".npmrc" \
  --exclude "bin/" \
  --exclude "build/" \
  --exclude "coverage/" \
  --exclude "dist/" \
  --exclude "node_modules/" \
  --exclude "tests/" \
  --exclude "vendor/" \
  --exclude "AGENTS.md" \
  --exclude ".phpunit.cache/" \
  --exclude ".phpunit.result.cache" \
  --exclude "phpunit.xml" \
  --exclude "phpunit.xml.dist" \
  --exclude "phpunit-unit.xml.dist" \
  --exclude "*.log" \
  --exclude "*.sql" \
  --exclude "*.sqlite" \
  --exclude "*.sqlite3" \
  --exclude "*.dump" \
  --exclude "*.bak" \
  --exclude "*.backup"

rm -f "${ZIP_FILE}"
(
  cd "${BUILD_DIR}"
  zip -qr "${ZIP_FILE}" "${PLUGIN_SLUG}"
)

rm -rf "${BUILD_DIR}"

echo "${ZIP_FILE}"
