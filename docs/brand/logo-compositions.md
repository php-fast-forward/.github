# PHP Fast Forward logo compositions

The [composition board](logo-compositions.html) shows real HTML applications of
the framework symbol, name, and Dash. The [reusable stylesheet](../../assets/styles/brand.css)
contains only scoped `ff-brand-*` components, with token fallbacks matching
[DESIGN.md](../../DESIGN.md). It requires no build, JavaScript, remote font, or
automatic network request.

## Composition hierarchy

| Application | Elements | Intended use |
| --- | --- | --- |
| Institutional | Compact chevrons + live PHP Fast Forward text | Navigation, technical identification, and neutral institutional surfaces. |
| Developer — default | Institutional signature + `dash-developer-welcome.png` | Framework identity, documentation, engineering, and principal mascot presence. |
| Editorial — secondary | Institutional signature + `dash-editorial-hoodie.png` | Community welcome, appreciation, and occasional institutional storytelling. |
| Avatar study | Complete developer pose + separate small chevrons + Dash label | A 256px composition study; a dedicated export still needs review at its actual delivery sizes. |

Dash, the Fast Forward fox, has a principal mature developer family and a new
secondary editorial family. Select one family for a composition. Do not mix
their finishes or use the editorial face to redefine the developer reference.
[SOUL.md](../../SOUL.md) defines character and purpose;
[STYLE.md](../../STYLE.md) defines the public voice.

## What the board delivers

These are **composed applications**, not newly flattened logo files. The browser
lays out a native SVG symbol, live text, and an independent PNG illustration.
Text remains selectable, and each image keeps its own provenance and format.

- `mark-compact.svg` is the native gradient chevron symbol.
- `mark-compact-ink.svg` and `mark-compact-white.svg` are native solid variants.
- `wordmark-light.png` is the existing supplied raster wordmark, without a fox.
- `dash-developer-welcome.png` and `dash-editorial-hoodie.png` supply the chosen
  character families.

The board does not embed PNGs inside a new SVG, trace the original lettering,
identify its font, or claim a complete vector logo master. The original raster
wordmark can remain in suitable light-surface applications. Native lettering
is separately tracked in [task #8](https://github.com/php-fast-forward/.github/issues/8).

## Reusable component contract

Load `brand.css` and put the composition inside `.ff-brand-page`. Token aliases
consume existing `--color-*`, `--radius-md`, and `--spacing-lg` values when they
are defined, falling back to DESIGN.md values. The lettering uses the specified
system sans-serif family list rather than a downloadable font.

| Class | Responsibility |
| --- | --- |
| `.ff-brand-canvas` / `.ff-brand-canvas--navy` | Light or navy application surface and readable foreground. |
| `.ff-brand-lockup` | Space between the independent identity and character. |
| `.ff-brand-identity` | Keeps the chevrons and live name together. |
| `.ff-brand-symbol` | Mark sizing and a clear-space area equal to 25% of mark height on each side. |
| `.ff-brand-name` | Selectable PHP Fast Forward name in a system-font treatment. |
| `.ff-brand-lockup--with-dash` / `.ff-brand-dash` | Principal character placement, proportionally sized. |
| `.ff-brand-lockup--editorial` | Secondary family application; selects no asset automatically. |
| `.ff-brand-avatar` / `.ff-brand-avatar-portrait` / `.ff-brand-avatar-signature` | Complete contained pose, with a separate small signature below it. |

```html
<div class="ff-brand-page">
  <div class="ff-brand-canvas">
    <div class="ff-brand-lockup">
      <div class="ff-brand-identity">
        <span class="ff-brand-symbol">
          <img src="/assets/brand/mark-compact-ink.svg" alt="" width="72" height="48">
        </span>
        <span class="ff-brand-name"><small>PHP</small>Fast Forward</span>
      </div>
    </div>
  </div>
</div>
```

Use the monochrome white symbol and live white text on navy. Use the ink symbol
or supplied gradient on light. Do not recolor raster artwork with CSS filters.
The `@theme` export in `assets/tokens/theme.css` needs a Tailwind processor; its
existence alone does not define browser custom properties. A consumer can use
the documented browser token adapter or let these fallbacks render the board.

## Geometry and accessibility

Keep the three chevrons facing right and preserve their relative sizes. The
symbol must remain separate from Dash's head: no replacement ears, cutout,
overlay on the face, or mascot silhouette used as a clipping mask. Keep the
complete character, original padding, ears, and tail visible. Add spacing rather
than stretching or clipping an illustration.

The board displays signature marks at 48px high with 12px clear space. The avatar
study uses a separate 24px-high native symbol in its footer; this does not
establish a new minimum for the full signature or the detailed character.
Below readable character sizes, use the institutional signature or a simple mark.

When live text identifies the product, the adjacent chevrons are decorative and
use empty alt text. Meaningful Dash artwork has an appropriate description.
Preserve keyboard focus, text contrast, meaningful links, and source order.
Narrow layouts stack the identity and character without changing the assets.
The board has no animation and retains a reduced-motion rule for consumers.

## Export and ownership

The design repository owns these composition rules, CSS, tokens, and asset
catalog. Consumers own placement, layout integration, routing, and publication.
An HTML application can be used as a source for a deliberately reviewed export;
record a new derivative, dimensions, source asset IDs, source commit, hashes,
transformation, and rights status before cataloging it.

A screenshot export is raster. A native SVG symbol remains vector. Combining a
vector symbol with a raster fox does not make the entire composition vector.
Keep typography-master work distinct from component styling and from artwork
generation. This board creates no new avatar file and publishes no site.

Consult the [asset guide](../../assets/README.md) for provenance and reuse status,
the [brand specimen](index.html) for character families, and the
[documentation pattern](documentation.html) for a consumer surface.
