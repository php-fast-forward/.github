#!/usr/bin/env bash
set -euo pipefail

dev_tools_bin="${DEV_TOOLS_BIN:-dev-tools}"

mkdir -p "$(dirname "${INPUT_OUTPUT_FILE}")"
"${dev_tools_bin}" changelog:show "${INPUT_VERSION}" --file="${INPUT_CHANGELOG_FILE}" > "${INPUT_OUTPUT_FILE}"
