#!/usr/bin/env bash
set -euo pipefail

base_ref="${INPUT_BASE_REF:-main}"
pull_request_number="${INPUT_PULL_REQUEST_NUMBER:-}"
allowed_conflicts=$'CHANGELOG.md\n.github/wiki'
resolved_count=0
skipped_count=0
failed_count=0
summary_file="${GITHUB_STEP_SUMMARY:-}"

append_summary() {
    local message="$1"

    if [ -n "${summary_file}" ]; then
        printf '%s\n' "${message}" >> "${summary_file}"
    else
        printf '%s\n' "${message}"
    fi
}

collect_pull_requests() {
    if [ -n "${pull_request_number}" ]; then
        gh pr view "${pull_request_number}" \
            --json number,title,url,baseRefName,headRefName,headRepositoryOwner,isCrossRepository,mergeable

        return
    fi

    gh pr list \
        --state open \
        --base "${base_ref}" \
        --json number,title,url,baseRefName,headRefName,headRepositoryOwner,isCrossRepository,mergeable
}

repository_url() {
    php -r '
        $composer = json_decode((string) file_get_contents("composer.json"), true);
        $support = is_array($composer) ? ($composer["support"] ?? []) : [];
        $source = is_array($support) ? ($support["source"] ?? null) : null;
        echo is_string($source) && "" !== $source ? $source : "https://github.com/" . getenv("GITHUB_REPOSITORY");
    '
}

is_allowed_conflict_scope() {
    local conflicts="$1"

    while IFS= read -r file; do
        if [ -z "${file}" ]; then
            continue
        fi

        if ! grep -Fx --quiet -- "${file}" <<< "${allowed_conflicts}"; then
            return 1
        fi
    done <<< "${conflicts}"

    return 0
}

dispatch_required_tests() {
    local head_ref="$1"

    if ! gh workflow view tests.yml >/dev/null 2>&1; then
        append_summary "  - tests dispatch skipped: tests.yml workflow was not found"

        return 0
    fi

    if gh workflow run tests.yml --ref "${head_ref}" -f publish-required-statuses=true >/dev/null 2>&1; then
        append_summary "  - tests dispatch requested with required status mirroring"

        return 0
    fi

    if gh workflow run tests.yml --ref "${head_ref}" >/dev/null 2>&1; then
        append_summary "  - tests dispatch requested without required status mirroring"

        return 0
    fi

    append_summary "  - failed: resolved branch was pushed, but tests.yml could not be dispatched"

    return 1
}

resolve_pull_request() {
    local number="$1"
    local title="$2"
    local url="$3"
    local head_ref="$4"
    local head_owner="$5"
    local cross_repository="$6"
    local pr_base_ref="$7"
    local mergeable="$8"

    append_summary "- PR #${number}: inspecting ${url}"

    if [ "${pr_base_ref}" != "${base_ref}" ]; then
        append_summary "  - skipped: base branch is \`${pr_base_ref}\`, expected \`${base_ref}\`"
        skipped_count=$((skipped_count + 1))

        return
    fi

    if [ "${cross_repository}" = "true" ] || [ "${head_owner}" != "${GITHUB_REPOSITORY_OWNER}" ]; then
        append_summary "  - skipped: pull request branch is outside this repository"
        skipped_count=$((skipped_count + 1))

        return
    fi

    if [ "${mergeable}" = "MERGEABLE" ]; then
        append_summary "  - skipped: GitHub currently reports the pull request as mergeable"
        skipped_count=$((skipped_count + 1))

        return
    fi

    local workdir
    workdir="$(mktemp -d)"
    trap 'rm -rf "${workdir}"' RETURN

    git clone --no-tags "https://x-access-token:${GH_TOKEN}@github.com/${GITHUB_REPOSITORY}.git" "${workdir}/repo" >/dev/null 2>&1
    git -C "${workdir}/repo" config user.name "github-actions[bot]"
    git -C "${workdir}/repo" config user.email "41898282+github-actions[bot]@users.noreply.github.com"
    git -C "${workdir}/repo" fetch --no-tags origin \
        "+refs/heads/${base_ref}:refs/remotes/origin/${base_ref}" \
        "+refs/heads/${head_ref}:refs/remotes/origin/${head_ref}" >/dev/null 2>&1
    git -C "${workdir}/repo" switch -C "${head_ref}" "refs/remotes/origin/${head_ref}" >/dev/null 2>&1

    if git -C "${workdir}/repo" merge --no-commit --no-ff "refs/remotes/origin/${base_ref}" >/dev/null 2>&1; then
        git -C "${workdir}/repo" merge --abort >/dev/null 2>&1 || true
        append_summary "  - skipped: merge succeeds cleanly when checked locally"
        skipped_count=$((skipped_count + 1))

        return
    fi

    local conflicts
    conflicts="$(git -C "${workdir}/repo" diff --name-only --diff-filter=U)"

    if [ -z "${conflicts}" ]; then
        git -C "${workdir}/repo" merge --abort >/dev/null 2>&1 || true
        append_summary "  - skipped: merge failed but no unmerged files were reported"
        skipped_count=$((skipped_count + 1))

        return
    fi

    if ! is_allowed_conflict_scope "${conflicts}"; then
        git -C "${workdir}/repo" merge --abort >/dev/null 2>&1 || true
        append_summary "  - skipped: conflict scope requires manual review"
        append_summary "$(printf '%s\n' "${conflicts}" | sed 's/^/    - `/; s/$/`/')"
        skipped_count=$((skipped_count + 1))

        return
    fi

    if grep -Fx --quiet -- ".github/wiki" <<< "${conflicts}"; then
        git -C "${workdir}/repo" checkout --ours -- .github/wiki
        git -C "${workdir}/repo" add .github/wiki
    fi

    if grep -Fx --quiet -- "CHANGELOG.md" <<< "${conflicts}"; then
        # During `git merge base into PR`, stage 2 is the PR side and stage 3 is the base branch side.
        git -C "${workdir}/repo" show :2:CHANGELOG.md > "${workdir}/CHANGELOG.ours.md"
        git -C "${workdir}/repo" show :3:CHANGELOG.md > "${workdir}/CHANGELOG.theirs.md"
        (
            cd "${workdir}/repo"
            php "${DEV_TOOLS_CONFLICT_RESOLVER}" \
                --target="${workdir}/CHANGELOG.theirs.md" \
                --source="${workdir}/CHANGELOG.ours.md" \
                --output="CHANGELOG.md" \
                --repository-url="$(repository_url)"
        )
        git -C "${workdir}/repo" add CHANGELOG.md
    fi

    if [ -n "$(git -C "${workdir}/repo" diff --name-only --diff-filter=U)" ]; then
        git -C "${workdir}/repo" merge --abort >/dev/null 2>&1 || true
        append_summary "  - failed: predictable files were handled, but unmerged paths remain"
        failed_count=$((failed_count + 1))

        return
    fi

    git -C "${workdir}/repo" commit -m "Resolve predictable conflicts with ${base_ref}" >/dev/null 2>&1
    git -C "${workdir}/repo" push origin "HEAD:${head_ref}" >/dev/null 2>&1
    append_summary "  - resolved: pushed an automatic conflict-resolution commit for \`${title}\`"

    if ! dispatch_required_tests "${head_ref}"; then
        failed_count=$((failed_count + 1))

        return
    fi

    resolved_count=$((resolved_count + 1))
}

if [ -z "${GH_TOKEN:-}" ]; then
    echo "GH_TOKEN is required." >&2

    exit 1
fi

append_summary "## Predictable Conflict Resolution Summary"
append_summary ""
append_summary "- Base branch: \`${base_ref}\`"

pull_requests="$(collect_pull_requests)"

if [ "${pull_requests:0:1}" = "{" ]; then
    pull_requests="[${pull_requests}]"
fi

while IFS= read -r pull_request; do
    [ -n "${pull_request}" ] || continue

    resolve_pull_request \
        "$(jq -r '.number' <<< "${pull_request}")" \
        "$(jq -r '.title' <<< "${pull_request}")" \
        "$(jq -r '.url' <<< "${pull_request}")" \
        "$(jq -r '.headRefName' <<< "${pull_request}")" \
        "$(jq -r '.headRepositoryOwner.login' <<< "${pull_request}")" \
        "$(jq -r '.isCrossRepository' <<< "${pull_request}")" \
        "$(jq -r '.baseRefName' <<< "${pull_request}")" \
        "$(jq -r '.mergeable // "UNKNOWN"' <<< "${pull_request}")"
done < <(jq -c '.[]' <<< "${pull_requests}")

append_summary ""
append_summary "- Resolved: ${resolved_count}"
append_summary "- Skipped: ${skipped_count}"
append_summary "- Failed: ${failed_count}"

if [ "${failed_count}" -gt 0 ]; then
    exit 1
fi
