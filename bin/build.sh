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

# Only what belongs in an install. An allowlist, not a denylist: excluding
# known rubbish means anything new that appears in the working tree ships by
# accident -- which is exactly how a set of screenshots and a browser
# automation log once ended up inside the package.
INCLUDE_FILES=(
	"moksa-line.php"
	"uninstall.php"
	"readme.txt"
	"LICENSE"
)

INCLUDE_DIRS=(
	"src"
	"views"
	"assets"
	"languages"
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

	local item
	for item in "${INCLUDE_FILES[@]}"; do
		if [[ ! -f "${ROOT}/${item}" ]]; then
			echo "Missing required file: ${item}" >&2
			exit 1
		fi

		cp "${ROOT}/${item}" "${STAGE}/"
	done

	for item in "${INCLUDE_DIRS[@]}"; do
		if [[ ! -d "${ROOT}/${item}" ]]; then
			echo "Missing required directory: ${item}" >&2
			exit 1
		fi

		mkdir -p "${STAGE}/${item}"
		# Copy the tree, then drop anything that is not a shipped asset type.
		tar -C "${ROOT}/${item}" -cf - . | tar -C "${STAGE}/${item}" -xf -
	done

	# Belt and braces: nothing hidden, and no development leftovers.
	find "${STAGE}" -name '.*' -not -name '.' -prune -exec rm -rf {} + 2>/dev/null || true
	find "${STAGE}" \( -name '*.log' -o -name '*.yml' -o -name '*.map' -o -name '*.zip' \) -delete 2>/dev/null || true

	# Syntax check what is actually going out, not what is in the working copy.
	if ! command -v php >/dev/null 2>&1; then
		echo "php is not on PATH, so the package cannot be syntax checked." >&2
		echo "Refusing to build unverified code. Add php to PATH and try again." >&2
		exit 1
	fi

	local file
	while IFS= read -r file; do
		if ! php -l "${file}" >/dev/null 2>&1; then
			echo "Syntax error in ${file}:" >&2
			php -l "${file}" >&2 || true
			exit 1
		fi
	done < <(find "${STAGE}" -name '*.php')

	# And the JavaScript. A broken .js does not stop the plugin loading, it just
	# makes an editor quietly stop working in the browser, which is how a string
	# containing a literal newline shipped once and was only found by opening
	# the console. node is optional so the build still works without it, but a
	# release should not be cut blind.
	if command -v node >/dev/null 2>&1; then
		while IFS= read -r file; do
			if ! node --check "${file}" >/dev/null 2>&1; then
				echo "Syntax error in ${file}:" >&2
				node --check "${file}" >&2 || true
				exit 1
			fi
		done < <(find "${STAGE}" -name '*.js')
	else
		echo "node is not on PATH, so the JavaScript was not syntax checked." >&2
	fi

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
