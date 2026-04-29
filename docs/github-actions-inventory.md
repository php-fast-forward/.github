# GitHub Actions Inventory

This inventory records the first shared automation extraction from
`php-fast-forward/dev-tools` into the organization `.github` repository.

## Shared reusable workflows

The following reusable workflows are copied into `.github/workflows/` because
they are called by consumer workflow wrappers synchronized by `dev-tools`:

- `auto-assign.yml`
- `auto-resolve-conflicts.yml`
- `changelog.yml`
- `label-sync.yml`
- `reports.yml`
- `review.yml`
- `tests.yml`
- `wiki-maintenance.yml`
- `wiki-preview.yml`

## Composite actions

The full `.github/actions/` tree from `dev-tools` is copied into this
repository because the reusable workflows depend on actions across these areas:

- changelog release automation
- GitHub Pages report previews
- predictable conflict resolution
- pull request label synchronization
- PHP and Composer setup
- project board synchronization
- review request rendering
- step summary rendering
- wiki preview and publication helpers

Reusable workflows reference these composite actions through remote
`php-fast-forward/.github/.github/actions/...@ref` action calls. Consumer
wrappers only choose the reusable workflow ref; the called workflow owns its
internal action refs.

This repository also adds `.github/actions/dev-tools/setup` so reusable
workflows can locate an existing project-local `dev-tools` binary or install
`fast-forward/dev-tools` globally when a consumer repository does not require
the package directly.

The PHP action group also includes `.github/actions/php/detect-project`, used by
test and report workflows to skip PHPUnit/report generation when a repository
does not have the Composer, PHPUnit, documentation, or test files those jobs
need.

The reusable workflows keep their original local triggers in this repository so
changes can be smoke-tested here before consumer wrappers are updated.

The changelog release workflow pins internal shared-action refs to `vX.Y.Z`
during `.github` release publication before creating the tag, keeping released
workflow tags immutable and compatible with Dependabot updates in consumers.

## Remaining in dev-tools

The `dev-tools` repository remains responsible for:

- the PHP CLI package and Composer plugin
- `dev-tools:sync`
- consumer workflow wrappers under `resources/github-actions/`
- future wrapper updates that point consumers at `php-fast-forward/.github`

Repository-local workflows in `dev-tools` that are not consumer-facing reusable
workflow implementations are intentionally not part of this initial extraction.
