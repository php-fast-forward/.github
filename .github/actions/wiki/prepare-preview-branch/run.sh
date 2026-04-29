#!/usr/bin/env bash
set -euo pipefail

preview_branch="${INPUT_PREVIEW_BRANCH}"

git fetch origin

if git ls-remote --exit-code --heads origin "${preview_branch}" >/dev/null 2>&1; then
    git switch -C "${preview_branch}" --track "origin/${preview_branch}"
    git reset --hard "origin/${preview_branch}"
else
    git switch --orphan "${preview_branch}"
    git rm -rf . >/dev/null 2>&1 || true
    find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
fi

git clean -fd
