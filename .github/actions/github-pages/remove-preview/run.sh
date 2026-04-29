#!/usr/bin/env bash
set -euo pipefail

pages_path="${INPUT_PATH}"
pull_request_number="${INPUT_PULL_REQUEST_NUMBER}"

rm -rf "${pages_path}/previews/pr-${pull_request_number}"

cd "${pages_path}"
touch .nojekyll
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git add -A
git diff --cached --quiet || git commit -m "chore: remove preview for PR #${pull_request_number}"
