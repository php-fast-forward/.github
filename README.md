<p align="center">
  <img src="./assets/brand/wordmark-light.png" alt="PHP Fast Forward" width="640">
</p>

# PHP Fast Forward identity and organization profile

This repository is the public home of PHP Fast Forward's visual identity,
mascot references, design assets, organization profile, and community defaults.
Framework code and tooling belong in their own
[organization repositories](https://github.com/php-fast-forward).

## Start here

| Source | Responsibility |
| --- | --- |
| [DESIGN.md](DESIGN.md) | Visual canon, UI tokens, logo applications, mascot anatomy, and pose/use matrix. |
| [STYLE.md](STYLE.md) | Public voice, language, terminology, and evidence in product claims. |
| [SOUL.md](SOUL.md) | Character, values, and the Fast Forward fox's relationship with people. |
| [Asset library](assets/README.md) | Reusable exports, metadata, provenance, and consumption rules. |
| [Asset manifest](assets/manifest.json) | Stable asset IDs, hashes, dimensions, and classifications. |
| [Visual specimen](docs/brand/index.html) | Browsable mascot matrix, light/dark previews, and reference families. |
| [Documentation pattern](docs/brand/documentation.html) | Executable reading/navigation/code style and reusable CSS. |
| [Logo compositions](docs/brand/logo-compositions.html) | Institutional, developer, editorial, and avatar applications. |
| [Dash artwork skill](skills/dash-art/SKILL.md) | Generate consistent repository illustrations using verified masters and recorded prompts. |
| [References](references/README.md) | Website concepts, package art, and icon references. |
| [Design assessment](docs/brand/assessment.md) | Audit findings, decisions, and next artwork/website work. |
| [Mascot production guide](docs/brand/mascot-production.md) | Reference stack, planned pose matrix, and reusable illustration briefs. |

<p align="center">
  <img src="./assets/mascot/dash-developer-welcome.png" alt="Dash, the Fast Forward fox, welcoming the reader in a purple hoodie" width="260">
</p>

Dash developer is the primary character reference; the new editorial collection
is a secondary choice. Both wear the shared purple hoodie; package emblems are
composed separately when needed. References have explicit statuses so
an old concept or divergent illustration cannot silently redefine the mascot.

Open `docs/brand/index.html` in a browser, or serve the repository with
`python3 -m http.server 8765 --bind 127.0.0.1` and visit
`http://127.0.0.1:8765/docs/brand/`. The specimen loads only repository files.

## Organization surfaces

- [profile/README.md](profile/README.md) is the public organization manifesto.
- [profile/assets](profile/assets) retains existing public image paths.
- [.github/FUNDING.yml](.github/FUNDING.yml) holds sponsorship links.
- [CONTRIBUTING.md](CONTRIBUTING.md), [SUPPORT.md](SUPPORT.md),
  [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), [SECURITY.md](SECURITY.md), and
  [ACCESSIBILITY.md](ACCESSIBILITY.md) provide community defaults.
- [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE) and
  [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) support
  issue and review preparation.

GitHub can inherit these defaults in organization repositories that do not
provide their own corresponding files. Repository-specific files take precedence;
templates and files are not copied into consumer clones. See
[GitHub's default community file rules](https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/creating-a-default-community-health-file).

This repository is public. Keep private materials in an appropriate private
repository or local archive. The ignored `backup/` is not a publication surface
or a dependency of the reusable kit. Artwork rights and authoring gaps remain
explicit in the catalog; no inferred license is assigned to supplied artwork.

## Validation

```sh
python3 scripts/validate-brand.py
python3 -m unittest discover -s tests -p 'test_*.py'
npx -y @google/design.md@0.4.0 lint DESIGN.md
```

The [asset guide](assets/README.md#token-exports) describes reproducible token
exports. The brand workflow verifies delivered files and validator failure
modes. Visual likeness and complete accessibility still require review.

Dash is the Fast Forward fox. The developer collection is the primary reference;
the newly authored cute editorial collection supports community and softer
institutional moments. Both have welcome, reading, guiding, and composing poses.
The original cute source-pack exports are retained in the ignored local backup;
their historical profile URLs remain available. See the
[production guide](docs/brand/mascot-production.md) and
[generation receipts](docs/brand/dash-generation.json) for exact references.

The versioned `dash-art` package is source for reuse, not a host installation.
Set `DASH_BRAND_ROOT` to this checkout when using it from another repository.
