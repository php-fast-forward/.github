# PHP Fast Forward logo compositions

Direction **F / Fox in motion** was selected by the maintainer on 2026-10-03: the right-facing fox, orange **Fast**, and a matching monochrome signature. The native production reconstruction is presented with its applications before publication.

The fox connects the framework to Dash. It is a flat brand symbol with a forward curl, not a character turnaround. Mature developer Dash remains the primary illustration; editorial Dash remains the secondary collection for welcoming and softer institutional moments.

## Production masters

- [Color signature](../../assets/brand/fast-forward-logo.svg) for light surfaces.
- [Dark-surface signature](../../assets/brand/fast-forward-logo-dark.svg), with white lettering and rear ear.
- [Ink signature](../../assets/brand/fast-forward-logo-ink.svg) and [white signature](../../assets/brand/fast-forward-logo-white.svg), each using one visible ink and transparent muzzle cutouts.
- Matching standalone marks: [color](../../assets/brand/fast-forward-mark.svg), [dark](../../assets/brand/fast-forward-mark-dark.svg), [ink](../../assets/brand/fast-forward-mark-ink.svg), [white](../../assets/brand/fast-forward-mark-white.svg).

All eight exports share geometry and spacing. [logo-source.json](../../assets/brand/logo-source.json) contains authored fox paths, font glyph outlines and layout. [build-brand-logos.py](../../scripts/build-brand-logos.py) generates the exports with the Python standard library; `--check` verifies reproducibility and source font digests. No installed font, remote font, live text, embedded raster, or generation service is needed to render the SVGs.

Lettering uses **Exo 2 Italic, weight 900**, outlined during authoring with fontTools. This is the selected production typeface, not an identification of the earlier bitmap lettering. Its original variable font, [OFL license](../../assets/brand/source/OFL-Exo2.txt), pinned upstream commit, version and file hashes are preserved in [source/font-source.json](../../assets/brand/source/font-source.json). The temporary authoring tool is not a runtime dependency of the repository.

## Applications and use

The [visual reference](logo-compositions.html) shows color and monochrome masters, symbol sizes, and the updated organization cover and documentation pattern. Use the complete signature at 200 pixels or wider; use the standalone mark at 32 pixels or wider when the name is already present. Reserve clear space of at least one quarter of the symbol height. Preserve the proportions and the gap between Fast and Forward.

The cover is a composed PNG export from [profile-banner.html](profile-banner.html): the reviewed SVG signature and selected raster Dash illustration remain independently editable. Documentation uses the same signature and shared navigation. Profiles use light/dark SVGs and retain English, Portuguese and Spanish language links.

## Retired artwork

Earlier logo files, the concept board and screenshots containing the previous global signature have been removed from the active library. Hash-verified recovery copies exist only in the ignored local backup. `archived_sources` in the asset manifest retains provenance metadata without distributing those files or requiring a local backup in CI. Raw generation briefs remain historical evidence; their source availability is annotated rather than rewriting the original prompt.

The [generation receipt](logo-generation.json) records direction F's selection and the native reconstruction. [GitHub Pages](pages.md) publishes the current preview kit through a staged artifact, excluding recovery backups.
