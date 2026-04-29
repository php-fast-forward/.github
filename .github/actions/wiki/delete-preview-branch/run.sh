#!/usr/bin/env bash
set -euo pipefail

preview_branch="${INPUT_PREVIEW_BRANCH}"

if git ls-remote --exit-code --heads origin "${preview_branch}" >/dev/null 2>&1; then
    git push origin --delete "${preview_branch}"
else
    echo "Wiki preview branch ${preview_branch} does not exist. Nothing to delete."
fi
