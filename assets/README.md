# PHP Fast Forward asset library

Start with [DESIGN.md](../DESIGN.md), [SOUL.md](../SOUL.md), and
[STYLE.md](../STYLE.md). The [visual specimen](../docs/brand/index.html) shows the
mascot matrix and the families side by side. The
[manifest](manifest.json) is the inventory of delivered bytes, provenance,
dimensions, formats, and classification.

## Source of truth

`assets/` holds the selected reusable identity. `references/` holds concepts and
historical variants for comparison. `profile/assets/` holds organization-profile
delivery exports. The selected logo has eight native SVG exports; full Dash
illustrations remain cataloged PNGs. Retired logos are excluded from the visible
kit and retained only in the ignored archive with `archived_sources` metadata.

“Canonical” means selected for visual consistency in this identity version.
It does not mean an author has granted a new license, a complete turnaround
exists, or every illustration is a native vector drawing. The historical source
pack records only that its artwork was created in a conversation; its authoring
and usage-rights gaps remain historical provenance. The current logo's native
master, generator, and official Exo 2 font source are recorded separately.
Package references distinguish verified public commit/blob provenance from
local tracked/untracked sources; public availability is not a redistribution license.
Do not infer artwork licensing from Composer metadata or a mockup's footer.
License/authoring gaps are recorded in the [assessment](../docs/brand/assessment.md).

## Reusable files

| File / collection | Role | Surface |
| --- | --- | --- |
| [brand/fast-forward-logo.svg](brand/fast-forward-logo.svg) / [dark](brand/fast-forward-logo-dark.svg) | Selected direction F, fox signature with orange “Fast” | Color signature on light / dark surfaces. |
| [brand/fast-forward-logo-ink.svg](brand/fast-forward-logo-ink.svg) / [white](brand/fast-forward-logo-white.svg) | Monochrome signature, same selected geometry | Ink on light / white on dark. |
| [brand/fast-forward-mark.svg](brand/fast-forward-mark.svg) / [dark](brand/fast-forward-mark-dark.svg) | Native standalone fox symbol | Small UI and symbol applications on light / dark. |
| [brand/fast-forward-mark-ink.svg](brand/fast-forward-mark-ink.svg) / [white](brand/fast-forward-mark-white.svg) | Monochrome standalone symbol | Ink on light / white on dark. |
| [mascot/dash-developer-welcome.png](mascot/dash-developer-welcome.png) | **Primary Dash master**, mature purple-hoodie family | Default package, documentation, institutional. |
| [Developer reading](mascot/dash-developer-reading.png), [guiding](mascot/dash-developer-guide.png), [composing](mascot/dash-developer-build.png) | Derivatives of the primary developer master | Choose a pose for the surrounding message. |
| [mascot/dash-editorial-hoodie.png](mascot/dash-editorial-hoodie.png) | **Secondary editorial master**, newly created cute family | Welcome, community, softer institutional moment. |
| [Editorial reading](mascot/dash-editorial-reading.png), [guiding](mascot/dash-editorial-guide.png), [composing](mascot/dash-editorial-build.png) | Derivatives of the editorial master | Preserve that master's face and painted finish. |
| [styles/documentation.css](styles/documentation.css) | Standalone documentation pattern and token adapter | Light/navy reading, prose, navigation, code. |
| [styles/brand.css](styles/brand.css) | Reusable logo and mascot compositions | See the [logo board](../docs/brand/logo-compositions.html). |
| [backgrounds/hero-trails.png](backgrounds/hero-trails.png) / [footer](backgrounds/footer-trails.png) | Supplied speed-trail surfaces | Decorative editorial backgrounds. |

Use one collection per composition. Developer is the default; editorial is an
intentional secondary choice. Original cute source-pack drawings and lockups
were archived locally under ignored `backup/dash/legacy-kit/`, with matching
hashes verified before removing them from the active kit. Retired logo files
are also archived with their provenance; their old lettering is not the current
font definition.

## Logo masters

Direction F is selected: the fox symbol and “Fast” use orange `#FF641B`; the
dark-surface signature uses white surrounding lettering, and the monochrome
variants preserve the same geometry. Gradients are optional decorative accents.
UI colors and full Dash fur/hoodie colors remain governed by [DESIGN.md](../DESIGN.md).

[brand/logo-source.json](brand/logo-source.json) is the editable source of truth
for symbol paths, lettering outlines, palette, and layout.
[build-brand-logos.py](../scripts/build-brand-logos.py) builds all eight SVGs
without fontTools or installed fonts at delivery time:

```sh
python3 scripts/build-brand-logos.py
python3 scripts/build-brand-logos.py --check
```

The lettering uses Exo 2 Italic, version 2.010, weight 900. The official variable
[font](brand/source/Exo2-Italic.ttf) is preserved byte for byte, with its
[SIL OFL 1.1 notice](brand/source/OFL-Exo2.txt). The
[font-source record](brand/source/font-source.json) pins the Google Fonts commit,
URLs, hashes, byte lengths, version, and in-memory outline authoring method.
Retain the font's notice and license when redistributing the font. Its license
does not establish rights for unrelated supplied artwork.

The selected production SVGs are separate from the earlier concept-generation
receipt. Selection and local preparation do not imply external publication.

## Status and provenance

| Status | Meaning |
| --- | --- |
| `canonical` | Selected current identity, primary Dash references, and reusable styles/marks. |
| `package-variant` | Secondary editorial references and local/public package variations, with explicit provenance. |
| `reference` | Composition/icon/layout reference; no product claims are inherited. |
| `legacy` | Historical profile delivery path retained for compatibility. |
| `exploratory` | Local proposal awaiting review; not a selected master or publication approval. |

Each manifest entry has a stable ID, file path, role, SHA-256, byte length,
format, status, and source. Raster metadata records the inspected dimensions,
mode, and alpha availability. SVG metadata records embedded raster presence.
Copied files keep the source SHA-256; derivatives explain the transformation.
No private project source text or illustrations were imported.

The ignored local `backup/` remains an archive, including Affinity documents and
unused exports. It is not required by consumers or validation. Do not add it
wholesale. The pre-existing untracked GitHub Actions social preview remains
outside this delivery; it was not silently promoted into canon.

## Updating the library

1. Choose the role and reference family before producing an asset.
2. Record the source, authoring method, rights evidence, and intended surface.
3. Add a new filename and manifest entry; preserve historical delivery URLs.
4. Inspect transparency, edges, anatomy, contrast, and readable size in the
   specimen. Newly generated poses are derivatives, not automatic canon.
5. Run `python3 scripts/validate-brand.py` and
   `python3 -m unittest discover -s tests -p 'test_*.py'` from the repository root.
6. For token changes, regenerate all exports and include the design lint result.

The validator verifies bytes and delivery metadata, rejects unsafe SVG content,
and checks that managed images are cataloged. It does not judge likeness,
copyright, alt-text quality, or complete accessibility. Those need review.

## Token exports

[DESIGN.md](../DESIGN.md) is normative. Generated files are
[tokens/tokens.json](tokens/tokens.json) (DTCG),
[tokens/tailwind.theme.json](tokens/tailwind.theme.json) (Tailwind v3), and
[tokens/theme.css](tokens/theme.css) (Tailwind v4).

The export tool was verified at version `0.4.0`. The portable helper runs lint,
exports all three formats, applies the versioned typography adapter, and records
source/output/adapter hashes in `exports.json`:

```sh
node scripts/export-design.mjs
node scripts/export-design.mjs --check
```

To prepare a token change while the browser adapter still has old values, use
`node scripts/export-design.mjs --tokens-only`, update the token declarations in
`assets/styles/documentation.css` and its manifest digest, then run `--check` and
the asset validator. This preparation mode is not the CI validation command.

CI uses `--check` to regenerate and compare the actual outputs with the committed
exports. Use the helper for committed outputs: direct CLI exports need the
compatibility adapter in `scripts/design-exports.mjs`.

CLI `0.4.0` drops bare numeric line heights, so the normative YAML quotes unitless
ratios. All styles declare letter spacing explicitly. The adapter separates font
fallbacks, converts relative `em` tracking to a supported DTCG dimension, and
adds the associated typography properties used by Tailwind v4 text utilities.
It rejects incomplete typography instead of publishing silently missing values.
See the [DTCG typography contract](https://www.designtokens.org/tr/2025.10/format/#typography)
and [Tailwind text metadata](https://tailwindcss.com/docs/font-size#customizing-your-theme).

For a standalone lint check:

```sh
npx -y @google/design.md@0.4.0 lint DESIGN.md
```

For RTK-enabled hosts, run these commands through RTK per the local bootstrap.
The export format follows the [DESIGN.md specification](https://github.com/google-labs-code/design.md).

## Consumption

Use repository-relative paths inside this repository. Other repositories should
link the central guidelines and, when copying an image, record its manifest ID,
source commit, and SHA-256. For remote embeds, pin the raw GitHub asset URL to a
commit so an unrelated future export cannot change an already released page.
Preserve asset provenance and any applicable notices with the copy.

Serve PNG as `image/png` and SVG as `image/svg+xml`. Give meaningful artwork
alt text. Decorative artwork has empty alt text. Use the dark color signature
or white monochrome variant on navy; choose the light color or ink variant on
light surfaces. Full signatures are outlined SVGs, while composed profile images
can combine these vectors with raster Dash artwork.

## Generate consistent Dash artwork

Use the versioned [dash-art skill](../skills/dash-art/SKILL.md), the
[production guide](../docs/brand/mascot-production.md), and actual master images.
The [generation receipts](../docs/brand/dash-generation.json) preserve exact
prompts, source hashes, edits, output identity, and the tool information exposed
during production. The built-in image generator was used; no model/version was
invented when the tool did not expose one. A file-integrity check does not prove
likeness or clear rights.

The skill is source in this repository; this change installs nothing in a host's
skill collection. A consumer can provide this checkout as `DASH_BRAND_ROOT`.
