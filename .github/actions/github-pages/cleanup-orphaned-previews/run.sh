#!/usr/bin/env bash
set -euo pipefail

pages_path="${INPUT_PATH}"

cd "${pages_path}"

deleted=0
skipped=0
unresolved=0

if [ ! -d previews ]; then
    echo "No previews directory exists. Nothing to clean."

    exit 0
fi

while read -r preview_dir; do
    branch="${preview_dir#previews/}"
    pull_request_number="${branch#pr-}"

    if ! [[ "${pull_request_number}" =~ ^[0-9]+$ ]]; then
        echo "Skipping preview directory ${preview_dir}: name does not match pr-<number>."
        skipped=$((skipped + 1))
        continue
    fi

    state="$(gh pr view "${pull_request_number}" --repo "${GITHUB_REPOSITORY}" --json state --jq '.state' 2>/dev/null || echo UNKNOWN)"

    case "${state}" in
        CLOSED|MERGED)
            echo "Deleting preview directory ${preview_dir} for ${state} pull request #${pull_request_number}."
            rm -rf "${preview_dir}"
            deleted=$((deleted + 1))
            ;;
        OPEN)
            echo "Keeping preview directory ${preview_dir} for open pull request #${pull_request_number}."
            skipped=$((skipped + 1))
            ;;
        *)
            echo "Could not resolve pull request #${pull_request_number} for preview directory ${preview_dir}. Keeping it."
            unresolved=$((unresolved + 1))
            ;;
    esac
done < <(find previews -mindepth 1 -maxdepth 1 -type d -name 'pr-*' | sort)

echo "Preview cleanup summary: deleted=${deleted}, skipped=${skipped}, unresolved=${unresolved}."

echo "deleted=${deleted}" >> "${GITHUB_OUTPUT}"
echo "skipped=${skipped}" >> "${GITHUB_OUTPUT}"
echo "unresolved=${unresolved}" >> "${GITHUB_OUTPUT}"

touch .nojekyll
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git add -A
git diff --cached --quiet || git commit -m "chore: remove orphaned pull request previews"
