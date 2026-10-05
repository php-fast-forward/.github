# Dash repository rollout

Audit date: 2026-10-05. This is an implementation receipt; linked PRs and CI states may change after the audit.

The organization had 40 repositories: 16 PHP libraries with source on `main`, one documentation template with Twig/CSS implementation, two resource/runtime projects with submitted implementation PRs, and this central identity repository. All 19 consumer repositories now have an open Dash README PR; this kit already includes Dash in its root and multilingual profiles through [PR #7](https://github.com/php-fast-forward/.github/pull/7).

Each consumer receives a distinct contextual Dash illustration under `docs/_static/mascot-banner.png`, presented at 840 pixels wide in the root README. The README references the image through its repository-relative path. The generic welcome copy is removed. Enum's `enum-mascot-banner.png` and framework's `mascot.png` are removed; Sphinx references use the shared replacement path. Dev-tools replaces its existing `mascot-banner.png` in place.

The [contextual artwork catalog](repository-artwork.json) records all 19 exact prompts, canonical reference hashes, distinct output hashes, dimensions, publication scope and pinned consumer source commits. Its central copies are the nineteen files under [`references/ecosystem/`](../../references/README.md). The earlier thirteen package references were retired into `archived_sources`, with the same source IDs and original bytes retained in the ignored local backup. Generation receipts and character guidance stay in this central kit; consumer artwork PRs contain no generation receipt or brand-provenance README. The developer welcome/build references remain canonical; these scenes are contextual applications. Source kit commit: `654e4a463533f1d8b8223369b3b0bbc1a4a0badf`. Character definitions remain owned by this kit and its [Dash artwork skill](../../skills/dash-art/SKILL.md).

The maintainer explicitly requested repository-specific artwork, public PRs and replacement of the old banners on 2026-10-05, then required a relative README image path and a uniform archive policy. All 19 consumers exclude `/docs/` and `/README*.md` through `.gitattributes`, keeping the README and its banner together outside package archives. The archive check confirmed both exclusions while preserving licenses and runtime files. This scope does not assert a general asset license or settle historical source rights. The documentation template's runtime identity assets belong to its separate design-system PR and remain included in its package.

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
| `phpdoc-bootstrap-template` | [#5](https://github.com/php-fast-forward/phpdoc-bootstrap-template/pull/5) |

The `agents` implementation is a Markdown resource bundle submitted in PR #2; the `github-actions` PHP runtime is submitted in PR #2. Their new README PRs target `main` independently, without modifying or merging those implementation PRs. The documentation template is included because its Twig/CSS is executable template code even though it has no PHP `src/` tree.

The other 20 repositories had no default branch or published tree and received no placeholder PR: `memoize`, `cache`, `logger`, `console`, `template-renderer`, `feature-toggle`, `promises`, `http-middleware`, `scheduler`, `http-content-negotiation`, `http-healthcheck`, `dockerfiles`, `http-cache`, `http-apcu`, `http-phpinfo`, `application`, `http-error-handler`, `runtime`, `php-fast-forward`, `http-server`.

Validation covered distinct PNG hashes and dimensions, canonical input hashes, image path and alt text, relative README targets, archive exclusion rules, stale-banner removal and clean text diffs. All 19 final consumer HEADs matched their push destinations and GitHub PR heads; image bytes remained unchanged during cleanup. The central references match those exact Git blobs and output receipts. `composer-installers` Composer metadata validation and `changelog` skill-link verification also passed. The latter has a unique documentation fragment; `framework` has an Unreleased entry per its contract.

The required local full checks in `framework` and `dev-tools` were attempted but failed in isolated checkouts without project dependencies; the globally resolved tool also had missing dependencies. No consumer PHP-suite or coverage pass is asserted. Repository CI/reviews remain separate from image validation. At the earlier verification, the existing Composer Audit gate failed in `enum`, `framework` and `dev-tools`; `dev-tools` additionally reported an unrelated Rector configuration error. Wiki automation updated only the wiki submodule pointer in `enum` and `dev-tools`; the contextual image hashes remained unchanged. Current check states are available on the linked PRs.

All PRs were opened and attached to the originating task. The documentation template's original artwork PR [#3](https://github.com/php-fast-forward/phpdoc-bootstrap-template/pull/3) was merged; its final README/archive cleanup is the open follow-up [#5](https://github.com/php-fast-forward/phpdoc-bootstrap-template/pull/5). The nineteen PRs linked above were open at the final audit. No release or deployment was performed by this rollout.
