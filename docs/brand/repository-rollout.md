# Dash repository rollout

Audit date: 2026-10-05. This is an implementation receipt; linked PRs and CI states may change after the audit.

The organization had 40 repositories: 16 PHP libraries with source on `main`, one documentation template with Twig/CSS implementation, two resource/runtime projects with submitted implementation PRs, and this central identity repository. All 19 consumer repositories now have an open Dash README PR; this kit already includes Dash in its root and multilingual profiles through [PR #7](https://github.com/php-fast-forward/.github/pull/7).

Each consumer receives a distinct contextual Dash illustration under `docs/_static/mascot-banner.png`, presented at 840 pixels wide in the root README. The README uses an immutable URL to the image in its own repository commit so archive consumers can still resolve the banner while documentation keeps the local path. The generic welcome copy is removed. Enum's `enum-mascot-banner.png` and framework's `mascot.png` are removed; Sphinx references use the shared replacement path. Dev-tools replaces its existing `mascot-banner.png` in place.

The [contextual artwork catalog](repository-artwork.json) records all 19 exact prompts, canonical reference hashes, distinct output hashes, dimensions and publication scope. Each consumer also carries `docs/_static/mascot-banner.receipt.json`. The developer welcome/build references remain canonical; these scenes are applications, not new anatomy masters. Source kit commit: `654e4a463533f1d8b8223369b3b0bbc1a4a0badf`. Character definitions remain owned by this kit and its [Dash artwork skill](../../skills/dash-art/SKILL.md).

The maintainer explicitly requested repository-specific artwork, public PRs and replacement of the old banners on 2026-10-05. This scope does not assert a general asset license or settle historical source rights. README-only artwork stays outside package archives; immutable hosted image targets prevent broken packaged README images. The documentation template's runtime identity assets belong to its separate design-system PR and remain included in its package.

Existing local working trees and unsubmitted artwork were not modified. All consumer work used isolated checkouts.

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

Validation covered distinct PNG hashes and dimensions, canonical input hashes, image path and alt text, immutable hosted README targets, archive exclusion rules, stale-banner removal and clean text diffs. `composer-installers` Composer metadata validation and `changelog` skill-link verification also passed. The latter has a unique documentation fragment; `framework` has an Unreleased entry per its contract.

The required local full checks in `framework` and `dev-tools` were attempted but failed in isolated checkouts without project dependencies; the globally resolved tool also had missing dependencies. No local PHP-suite or coverage pass is asserted. Repository CI/reviews remain separate from image validation. At verification, the existing Composer Audit gate failed in `enum`, `framework` and `dev-tools`; `dev-tools` additionally reported an unrelated Rector configuration error. Wiki automation updated only the wiki submodule pointer in `enum` and `dev-tools`; the contextual artwork and generation receipts remain intact. These failures are recorded in their PRs, without unrelated PHP/dependency changes.

All PRs were opened and attached to the originating task. No consumer PR was merged, and no release or deployment was performed by this rollout.
