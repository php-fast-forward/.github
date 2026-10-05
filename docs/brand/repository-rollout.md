# Dash repository rollout

Audit date: 2026-10-05. This is an implementation receipt; linked PRs and CI states may change after the audit.

The organization had 40 repositories: 16 PHP libraries with source on `main`, one documentation template with Twig/CSS implementation, two resource/runtime projects with submitted implementation PRs, and this central identity repository. All 19 consumer repositories now have an open Dash README PR; this kit already includes Dash in its root and multilingual profiles through [PR #7](https://github.com/php-fast-forward/.github/pull/7).

Every consumer receives a byte-identical copy of the canonical `dash-developer-welcome` master under `assets/brand/dash.png`, a descriptive image at 320 pixels wide in its root README, and a provenance receipt beside the PNG. Source commit: `e1d43e2af51bcb7aa0b48613830a18d5fa5d4536`; SHA-256: `38b2c5bb87c94326bcab6463f74fc785414d8539e9175e088b5a5c797a466e09`. Character and rights definitions remain owned by this kit and its [Dash artwork skill](../../skills/dash-art/SKILL.md).

Each receipt records the maintainer's explicit 2026-10-05 authorization to copy and publicly display Dash in these repository READMEs. That scope does not assert a general asset license or settle historical source rights. `/assets/brand/ export-ignore` keeps the README-only artwork out of package archives while preserving it on GitHub; archive contents were checked after commit.

Local banners were inventoried alongside remote images. Historical banners contain inconsistent eyes, face proportions or hoodie marks; they were preserved, while the approved master supplies a consistent README identity. Existing local working trees and unsubmitted artwork were not modified. All consumer work used isolated checkouts.

| Repository | README PR |
| --- | --- |
| `agents` | [#4](https://github.com/php-fast-forward/agents/pull/4) |
| `changelog` | [#12](https://github.com/php-fast-forward/changelog/pull/12) |
| `clock` | [#1](https://github.com/php-fast-forward/clock/pull/1) |
| `composer-installers` | [#10](https://github.com/php-fast-forward/composer-installers/pull/10) |
| `config` | [#4](https://github.com/php-fast-forward/config/pull/4) |
| `container` | [#7](https://github.com/php-fast-forward/container/pull/7) |
| `defer` | [#1](https://github.com/php-fast-forward/defer/pull/1) |
| `dev-tools` | [#360](https://github.com/php-fast-forward/dev-tools/pull/360) |
| `enum` | [#5](https://github.com/php-fast-forward/enum/pull/5) |
| `event-dispatcher` | [#1](https://github.com/php-fast-forward/event-dispatcher/pull/1) |
| `fork` | [#2](https://github.com/php-fast-forward/fork/pull/2) |
| `framework` | [#9](https://github.com/php-fast-forward/framework/pull/9) |
| `github-actions` | [#3](https://github.com/php-fast-forward/github-actions/pull/3) |
| `http` | [#1](https://github.com/php-fast-forward/http/pull/1) |
| `http-client` | [#1](https://github.com/php-fast-forward/http-client/pull/1) |
| `http-factory` | [#1](https://github.com/php-fast-forward/http-factory/pull/1) |
| `http-message` | [#1](https://github.com/php-fast-forward/http-message/pull/1) |
| `iterators` | [#1](https://github.com/php-fast-forward/iterators/pull/1) |
| `phpdoc-bootstrap-template` | [#3](https://github.com/php-fast-forward/phpdoc-bootstrap-template/pull/3) |

The `agents` implementation is a Markdown resource bundle submitted in PR #2; the `github-actions` PHP runtime is submitted in PR #2. Their new README PRs target `main` independently, without modifying or merging those implementation PRs. The documentation template is included because its Twig/CSS is executable template code even though it has no PHP `src/` tree.

The other 20 repositories had no default branch or published tree and received no placeholder PR: `memoize`, `cache`, `logger`, `console`, `template-renderer`, `feature-toggle`, `promises`, `http-middleware`, `scheduler`, `http-content-negotiation`, `http-healthcheck`, `dockerfiles`, `http-cache`, `http-apcu`, `http-phpinfo`, `application`, `http-error-handler`, `runtime`, `php-fast-forward`, `http-server`.

Validation covered the PNG signature/chunk checksums, exact source hash, image path and alt text, preservation of remaining README content, and clean text diffs. `composer-installers` Composer metadata validation and `changelog` skill-link verification also passed. The latter has a unique documentation fragment; `framework` has an Unreleased entry per its contract.

The required local full checks in `framework` and `dev-tools` were attempted but failed in isolated checkouts without project dependencies; the globally resolved tool also had missing dependencies. No local PHP-suite or coverage pass is asserted. Repository CI/reviews remain separate from image validation. At verification, the existing Composer Audit gate failed in `enum`, `framework` and `dev-tools`; `dev-tools` additionally reported an unrelated Rector configuration error. Wiki automation updated only the wiki submodule pointer in `enum` and `dev-tools`; the copied artwork and source receipts remain unchanged. These failures are recorded in their PRs, without unrelated PHP/dependency changes.

All PRs were opened and attached to the originating task. No consumer PR, release or deployment was merged or published by this rollout.
