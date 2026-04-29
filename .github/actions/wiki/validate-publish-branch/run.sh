#!/usr/bin/env bash
set -euo pipefail

publish_branch="${INPUT_PUBLISH_BRANCH}"
preview_branch="${INPUT_PREVIEW_BRANCH}"
expected_preview_sha="${INPUT_EXPECTED_PREVIEW_SHA}"
actual_publish_sha="$(git ls-remote origin "refs/heads/${publish_branch}" | awk '{print $1}')"

echo "Expected wiki publish SHA: ${expected_preview_sha}"
echo "Actual wiki publish SHA: ${actual_publish_sha}"

if [ -z "${actual_publish_sha}" ]; then
    echo "Remote wiki publish branch ${publish_branch} was not found after push." >&2
    exit 1
fi

if [ "${actual_publish_sha}" != "${expected_preview_sha}" ]; then
    echo "Remote wiki publish branch ${publish_branch} does not match preview branch ${preview_branch}." >&2
    exit 1
fi
