#!/usr/bin/env bash
set -euo pipefail

dev_tools_bin="${DEV_TOOLS_BIN:-dev-tools}"

if [ -n "${INPUT_VERSION}" ]; then
    version="${INPUT_VERSION}"
    source="input"
else
    version="$("${dev_tools_bin}" changelog:next-version --file="${INPUT_CHANGELOG_FILE}")"
    source="inferred"
fi

echo "value=${version}" >> "${GITHUB_OUTPUT}"
echo "source=${source}" >> "${GITHUB_OUTPUT}"
