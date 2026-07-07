#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="${ROOT_DIR}/crm-leads-capture.php"
CHANGELOG_FILE="${ROOT_DIR}/CHANGELOG.md"
REQUESTED="${1:-patch}"

current_version="$(sed -nE 's/^[[:space:]*]*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*$/\1/ip' "${MAIN_FILE}" | head -n 1 | tr -d '\r')"

if [[ -z "${current_version}" ]]; then
  echo "Could not detect current plugin version." >&2
  exit 1
fi

IFS='.' read -r major minor patch <<< "${current_version}"
current_major="${major}"
current_minor="${minor}"
current_patch="${patch}"

case "${REQUESTED}" in
  patch)
    patch=$((patch + 1))
    next_version="${major}.${minor}.${patch}"
    ;;
  minor)
    minor=$((minor + 1))
    next_version="${major}.${minor}.0"
    ;;
  major)
    major=$((major + 1))
    next_version="${major}.0.0"
    ;;
  [0-9]*.[0-9]*.[0-9]*)
    next_version="${REQUESTED}"
    ;;
  v[0-9]*.[0-9]*.[0-9]*)
    next_version="${REQUESTED#v}"
    ;;
  *)
    echo "Usage: bin/prepare-release.sh patch|minor|major|X.Y.Z|vX.Y.Z" >&2
    exit 1
    ;;
esac

if [[ ! "${next_version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid release version: ${next_version}" >&2
  exit 1
fi

IFS='.' read -r next_major next_minor next_patch <<< "${next_version}"

if (( next_major < current_major )) ||
  (( next_major == current_major && next_minor < current_minor )) ||
  (( next_major == current_major && next_minor == current_minor && next_patch <= current_patch )); then
  echo "Release version ${next_version} must be greater than current version ${current_version}." >&2
  exit 1
fi

perl -0pi -e "s/(^[ \\t*]*Version:[ \\t]*)\\Q${current_version}\\E/\${1}${next_version}/m" "${MAIN_FILE}"
perl -0pi -e "s/define\\( 'CRM_LEADS_CAPTURE_VERSION', '\\Q${current_version}\\E' \\);/define( 'CRM_LEADS_CAPTURE_VERSION', '${next_version}' );/" "${MAIN_FILE}"

if ! grep -q "^## ${next_version} " "${CHANGELOG_FILE}"; then
  tmp_file="$(mktemp)"
  awk -v version="${next_version}" '
    BEGIN { inserted = 0 }
    /^## / && inserted == 0 {
      print "## " version " - release"
      print ""
      print "- Preparacao de release."
      print ""
      inserted = 1
    }
    { print }
  ' "${CHANGELOG_FILE}" > "${tmp_file}"
  mv "${tmp_file}" "${CHANGELOG_FILE}"
fi

echo "${next_version}"
