# PHP Fast Forward asset library

Start with [design.md](../design.md), [soul.md](../soul.md), and
[style.md](../style.md). The [visual specimen](../docs/brand/index.html) shows the
mascot matrix and the families side by side. The
[manifest](manifest.json) is the inventory of delivered bytes, provenance,
dimensions, formats, and classification.

## Source of truth

`assets/` holds the selected reusable identity. `references/` holds concepts and
variants for comparison. `profile/assets/` retains historical consumer URLs.
The old SVG wordmarks and waving mascot embed raster images: they are legacy containers,
not vector masters. New consumers should use the cataloged PNG exports.

“Canonical” means selected for visual consistency in this identity version.
It does not mean an author has granted a new license, a complete turnaround
exists, or the art is a native vector drawing. The source pack records only that
the artwork was created in a conversation. Author, generator/version, font,
and asset usage rights were not recorded. Package references have verified
public commit/blob provenance; public availability is not a redistribution license.
Do not infer artwork licensing from Composer metadata or a mockup's footer.
License/authoring gaps are recorded in the [assessment](../docs/brand/assessment.md).

## Reusable files

| File | Role | Surface |
| --- | --- | --- |
| [brand/mark.png](brand/mark.png) | Three forward chevrons | Light or navy; keep clear space. |
| [brand/mark-with-fox.png](brand/mark-with-fox.png) | Framework symbol with fox | Light or navy, at a readable size. |
| [brand/wordmark-light.png](brand/wordmark-light.png) | Text lockup without fox | Light only; dark PHP lettering. |
| [brand/wordmark-with-fox-light.png](brand/wordmark-with-fox-light.png) | Text lockup with fox | Light only. |
| [brand/avatar-navy.png](brand/avatar-navy.png) | Square avatar | Self-contained navy background. |
| [mascot/fox-welcome.png](mascot/fox-welcome.png) | Primary neutral character reference | Welcome, appreciation, community. |
| [mascot/fox-reading-wave.png](mascot/fox-reading-wave.png) | Reading + wave | First steps and onboarding. |
| [mascot/fox-reading-sparkles.png](mascot/fox-reading-sparkles.png) | Reading + questions | Documentation and concepts. |
| [backgrounds/hero-trails.png](backgrounds/hero-trails.png) | Wide dark artwork | Hero backdrop; text needs a clear panel. |
| [backgrounds/footer-trails.png](backgrounds/footer-trails.png) | Dark footer artwork | Community section background. |
| [backgrounds/fox-forward.png](backgrounds/fox-forward.png) | Fox + mark composition | Editorial hero; retain its aspect ratio. |

Standalone characters and transparent lockups use real alpha. No recropping,
upscaling, or recoloring was performed during import. Their supplied padding
remains part of the export. Inspect the specimen on both light and dark surfaces.

## Status and provenance

| Status | Meaning |
| --- | --- |
| `canonical` | Selected neutral assets for new framework work. |
| `package-variant` | Existing public package artwork; clothing, emblem, or rendering may differ. |
| `reference` | Composition/icon/layout reference; no product claims are inherited. |
| `exploratory` | Divergent appearance; compare it, do not use it to redefine the fox. |
| `legacy` | Historical profile delivery path retained for compatibility. |

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

[design.md](../design.md) is normative. Generated files are
[tokens/tokens.json](tokens/tokens.json) (DTCG),
[tokens/tailwind.theme.json](tokens/tailwind.theme.json) (Tailwind v3), and
[tokens/theme.css](tokens/theme.css) (Tailwind v4).

The export tool was verified at version `0.4.0`. The portable helper runs lint,
exports all three formats, and records source/output hashes in `exports.json`:

```sh
node scripts/export-design.mjs
node scripts/export-design.mjs --check
```

CI uses `--check` to regenerate and compare the actual outputs with the committed
exports. The equivalent pinned CLI commands are:

```sh
npx -y @google/design.md@0.4.0 lint design.md
npx -y @google/design.md@0.4.0 export --format dtcg design.md > assets/tokens/tokens.json
npx -y @google/design.md@0.4.0 export --format json-tailwind design.md > assets/tokens/tailwind.theme.json
npx -y @google/design.md@0.4.0 export --format css-tailwind design.md > assets/tokens/theme.css
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
alt text. Decorative artwork has empty alt text. Use the mark plus live white
text for navy layouts until a dark wordmark master is produced.
