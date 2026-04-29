#!/usr/bin/env bash
set -euo pipefail

publish_branch="${INPUT_PUBLISH_BRANCH}"
preview_branch="${INPUT_PREVIEW_BRANCH}"

git fetch origin "${publish_branch}" "${preview_branch}"

expected_preview_sha="$(git rev-parse "origin/${preview_branch}")"
echo "expected-preview-sha=${expected_preview_sha}" >> "${GITHUB_OUTPUT}"
echo "Expected wiki preview SHA: ${expected_preview_sha}"

git switch -C "${publish_branch}" --track "origin/${publish_branch}" || git switch "${publish_branch}"
git reset --hard "origin/${preview_branch}"
git clean -fd
