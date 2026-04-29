#!/usr/bin/env bash
set -euo pipefail

version="${INPUT_HEAD_REF#${INPUT_RELEASE_BRANCH_PREFIX}}"

if [ -z "${version}" ] || [ "${version}" = "${INPUT_HEAD_REF}" ]; then
    echo "Failed to derive the release version from ${INPUT_HEAD_REF}." >&2
    exit 1
fi

echo "value=${version}" >> "${GITHUB_OUTPUT}"
