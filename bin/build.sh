#!/usr/bin/env bash
#
# Build the distributable zip.
#
# wordpress.org takes the plugin slug from the folder inside the zip, and that
# folder must match the text domain. This script writes that folder name
# explicitly rather than inheriting whatever the working copy happens to be
# called, so a checkout directory named anything at all still produces a
# correct package.

set -euo pipefail

SLUG="moksa-line"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

# Everything that has no business in an install.
EXCLUDES=(
	".git"
	".github"
	".gitignore"
	"bin"
	"dist"
	"tests"
	"node_modules"
	"vendor"
	"CHANGELOG.md"
	"README.md"
)

version_from_header() {
	grep -m1 -E '^\s*\*\s*Version:' "${ROOT}/${SLUG}.php" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'
}

version_from_readme() {
	grep -m1 -E '^Stable tag:' "${ROOT}/readme.txt" | sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '[:space:]'
}

main() {
	local header_version readme_version
	header_version="$(version_from_header)"
	readme_version="$(version_from_readme)"

	# A stable tag that disagrees with the plugin header is the single most
	# common reason a wordpress.org release ships the wrong code.
	if [[ "${header_version}" != "${readme_version}" ]]; then
		echo "Version mismatch: ${SLUG}.php says ${header_version}, readme.txt Stable tag says ${readme_version}." >&2
		exit 1
	fi

	echo "Building ${SLUG} ${header_version}"

	rm -rf "${DIST}"
	mkdir -p "${STAGE}"

	local rsync_args=(-a)
	local item
	for item in "${EXCLUDES[@]}"; do
		rsync_args+=(--exclude "${item}")
	done

	if command -v rsync >/dev/null 2>&1; then
		rsync "${rsync_args[@]}" "${ROOT}/" "${STAGE}/"
	else
		# Windows Git Bash often has no rsync; fall back to tar.
		local tar_excludes=()
		for item in "${EXCLUDES[@]}"; do
			tar_excludes+=(--exclude="./${item}")
		done
		tar -C "${ROOT}" "${tar_excludes[@]}" -cf - . | tar -C "${STAGE}" -xf -
	fi

	# Syntax check what is actually going out, not what is in the working copy.
	local file
	while IFS= read -r file; do
		php -l "${file}" >/dev/null || { echo "Syntax error in ${file}" >&2; exit 1; }
	done < <(find "${STAGE}" -name '*.php')

	if command -v zip >/dev/null 2>&1; then
		( cd "${DIST}" && zip -qr "${SLUG}.zip" "${SLUG}" )
	else
		# Windows Git Bash ships no zip, and PowerShell cannot read the
		# /c/... paths this shell uses, so translate them first.
		local shell_to_windows="cat"
		command -v cygpath >/dev/null 2>&1 && shell_to_windows="cygpath -w"

		local win_stage win_zip powershell_bin=""
		win_stage="$(${shell_to_windows} "${STAGE}")"
		win_zip="$(${shell_to_windows} "${DIST}/${SLUG}.zip")"

		command -v pwsh >/dev/null 2>&1 && powershell_bin="pwsh"
		[[ -z "${powershell_bin}" ]] && command -v powershell >/dev/null 2>&1 && powershell_bin="powershell"

		if [[ -z "${powershell_bin}" ]]; then
			echo "No zip tool found. The staged folder is at ${STAGE}." >&2
			exit 1
		fi

		"${powershell_bin}" -NoProfile -Command \
			"Compress-Archive -Path '${win_stage}' -DestinationPath '${win_zip}' -Force"
	fi

	echo "Wrote dist/${SLUG}.zip"
	echo "Files: $(find "${STAGE}" -type f | wc -l)"
}

main "$@"
