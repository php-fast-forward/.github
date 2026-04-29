#!/usr/bin/env bash
set -euo pipefail

release_tag="v${INPUT_VERSION}"

if gh release view "${release_tag}" --repo "${GITHUB_REPOSITORY}" >/dev/null 2>&1; then
    operation="updated"
    gh release edit "${release_tag}" \
        --repo "${GITHUB_REPOSITORY}" \
        --title "${release_tag}" \
        --notes-file "${INPUT_NOTES_FILE}"
else
    operation="created"
    gh release create "${release_tag}" \
        --repo "${GITHUB_REPOSITORY}" \
        --target "${INPUT_TARGET}" \
        --title "${release_tag}" \
        --notes-file "${INPUT_NOTES_FILE}"
fi

release_url="$(gh release view "${release_tag}" --repo "${GITHUB_REPOSITORY}" --json url --jq '.url')"

echo "operation=${operation}" >> "${GITHUB_OUTPUT}"
echo "url=${release_url}" >> "${GITHUB_OUTPUT}"
