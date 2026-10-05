---
version: alpha
name: PHP Fast Forward
description: Clear PHP foundations, a curious orange fox, and visible forward momentum.
colors:
  primary: "#6D28D9"
  primary-hover: "#5B21B6"
  blue: "#2563EB"
  cyan: "#22D3EE"
  navy: "#050B20"
  ink: "#142033"
  muted: "#475569"
  neutral: "#F6F8FF"
  white: "#FFFFFF"
  border: "#CBD5E1"
  fox-orange: "#F28D1A"
  fox-cream: "#FFF3DF"
  fox-outline: "#592D19"
typography:
  h1:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 3rem
    fontWeight: 750
    lineHeight: "1.1"
    letterSpacing: "-0.03em"
  h2:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 2rem
    fontWeight: 700
    lineHeight: "1.2"
    letterSpacing: 0px
  body-md:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 1rem
    fontWeight: 400
    lineHeight: "1.6"
    letterSpacing: 0px
  label:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 0.875rem
    fontWeight: 600
    lineHeight: "1.4"
    letterSpacing: 0px
  code:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: "1.6"
    letterSpacing: 0px
rounded:
  sm: 6px
  md: 12px
  lg: 20px
  pill: 999px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
  3xl: 64px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.white}"
    rounded: "{rounded.sm}"
    padding: 12px
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.white}"
  button-secondary:
    backgroundColor: "{colors.cyan}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: 12px
  page:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
  card:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "{spacing.lg}"
  caption:
    backgroundColor: "{colors.white}"
    textColor: "{colors.muted}"
    typography: "{typography.label}"
  hero:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    typography: "{typography.h1}"
  code-panel:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "{spacing.md}"
    typography: "{typography.code}"
  link-light:
    backgroundColor: "{colors.white}"
    textColor: "{colors.blue}"
  neutral-divider:
    backgroundColor: "{colors.border}"
    textColor: "{colors.ink}"
  mascot-orange-label:
    backgroundColor: "{colors.fox-orange}"
    textColor: "{colors.ink}"
  mascot-cream-label:
    backgroundColor: "{colors.fox-cream}"
    textColor: "{colors.fox-outline}"
---

## Overview

This is the normative visual system for PHP Fast Forward. The values above are
UI design decisions based on the supplied artwork, not claims that every raster
pixel matches those colors. [SOUL.md](SOUL.md) defines the character's purpose;
[STYLE.md](STYLE.md) defines the voice. The [asset catalog](assets/README.md)
records the actual reusable files and their provenance.

The selected framework signature is direction F: a simplified fox symbol beside
“PHP FastForward”, with the symbol and “Fast” in orange. Dash's complete character
illustrations help people learn and participate; dark speed trails can express
momentum in editorial artwork. Keep everyday documentation calm and readable.

The [brand specimen](docs/brand/index.html) presents Dash's pose matrix, palette,
and reference families. The [documentation pattern](docs/brand/documentation.md)
and [logo compositions](docs/brand/logo-compositions.md) define reusable applications.

## Colors

| Role | Token | Application |
| --- | --- | --- |
| Interactive emphasis | `primary` / `primary-hover` | Buttons and selected navigation on light surfaces. |
| Optional decorative gradient | `primary` → `blue` → `cyan` | Editorial accents; a gradient is not required by the selected logo. |
| Logo orange | `#FF641B` in `assets/brand/logo-source.json` | Fox symbol and “Fast” in the color signatures; separate from UI and fur tokens. |
| Dark stage | `navy` | Hero, code panels, and illustration backgrounds. |
| Reading surfaces | `neutral`, `white` | Documentation and cards. |
| Text | `ink`, `muted` | Headings/body and supporting copy on light surfaces. |
| Structure | `border` | Quiet separators; not sufficient alone to identify a control. |
| Character shorthand | `fox-orange`, `fox-cream`, `fox-outline` | Swatches for orange fur, cream markings, and warm brown contour. |

Use solid violet with white text for the primary action. Cyan and orange accents
take dark text. Decorative gradients are not reliable text backgrounds.
Use a visible outline or filled boundary where control recognition needs it.

Text pairs must meet 4.5:1 for normal text and 3:1 for large text; UI boundaries
and focus indicators need 3:1 against adjacent colors. Color alone must not
communicate a state. These rules follow
[WCAG contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
Token lint covers declared component text pairs, not complete-page accessibility.

## Typography

Use the system sans-serif stack for interfaces and the system monospace stack
for code. The selected logo lettering uses Exo 2 Italic, version 2.010, at weight
900. Its glyphs are outlined in the SVGs, so rendering the logo requires no font
installation or browser font request. This lettering does not change the UI
font stacks. The official unmodified variable font, SIL OFL 1.1 notice, pinned
source commit, and byte hashes live in [brand/source](assets/brand/source/font-source.json).

Use one H1 per page, sentence case, and a readable body line height of 1.6.
Scale the display heading down to 2rem on small screens. Keep installation
commands as selectable text. A picture of code never replaces a working example.

## Layout

Use the 4px spacing scale. Content pages use a centered width of at most 1200px;
reading prose uses about 70 characters per line. Give mobile pages at least
16px horizontal padding. Cards can form a grid, but preserve their reading order.

Website reference direction: a navy hero with a concise promise, one install
command, a primary documentation action, and a supporting ecosystem action;
then a light package grid, an installation example, and a community section.
Documentation reference direction: a navigation rail, reading column, code
panels, and a secondary table of contents. On narrow screens the reading column
comes first and navigation can collapse into a keyboard-operable control.

These are design patterns. The archived concepts' statistics, versions,
dependency claims, fictional API, and social links do not establish shipped
features. Verify actual package documentation before implementing a website.

## Elevation & Depth

Use thin borders and restrained shadows to group content. Reserve glow and
light trails for hero or campaign artwork. Place text on an opaque panel when
artwork would reduce contrast. Package illustrations use the primary developer family. Keep the secondary
editorial family separate within a composition.

## Shapes

Use gently rounded rectangles for cards and code panels. Keep the selected fox
symbol's native silhouette. Keep illustrations proportional, preserve transparent padding, and avoid
stretching, mirroring, or clipping the fox's ears and tail.

Dash has large triangular ears with pink/cream inner fur and brown rims, a
cream muzzle and cheeks, a small dark nose, warm amber/brown eyes, an orange
forehead tuft, dark paws, and exactly one full cream-tipped tail. The shared
costume is a violet hoodie with two cream drawstrings and a plain chest.

The primary reference is [dash-developer-welcome.png](assets/mascot/dash-developer-welcome.png).
Use its upright proportions, longer muzzle, face, dimensional fur, and cloth
rendering for new package and default institutional illustrations. The reading,
guide, and build poses corroborate this master. The secondary
[dash-editorial-hoodie.png](assets/mascot/dash-editorial-hoodie.png) anchors the
softer editorial collection: rounder face, compact seated proportions, larger
eyes, and painted brown contours. Choose that collection deliberately for
welcome, community, or a softer institutional context. Do not blend the models.

These are authored reference poses, not an orthographic turnaround. New side/back
views remain new derivatives; none can be inferred from a banner alone.

## Components

**Logo.** Direction F is the selected fox signature, with “Fast” in orange.
The eight exports contain native vector geometry and outlined lettering.

| Application | Full signature | Standalone mark |
| --- | --- | --- |
| Light surfaces, color | [fast-forward-logo.svg](assets/brand/fast-forward-logo.svg) | [fast-forward-mark.svg](assets/brand/fast-forward-mark.svg) |
| Dark surfaces, color | [fast-forward-logo-dark.svg](assets/brand/fast-forward-logo-dark.svg) | [fast-forward-mark-dark.svg](assets/brand/fast-forward-mark-dark.svg) |
| Light surfaces, monochrome | [fast-forward-logo-ink.svg](assets/brand/fast-forward-logo-ink.svg) | [fast-forward-mark-ink.svg](assets/brand/fast-forward-mark-ink.svg) |
| Dark surfaces, monochrome | [fast-forward-logo-white.svg](assets/brand/fast-forward-logo-white.svg) | [fast-forward-mark-white.svg](assets/brand/fast-forward-mark-white.svg) |

Edit [logo-source.json](assets/brand/logo-source.json), then rebuild with
[build-brand-logos.php](scripts/build-brand-logos.php); do not redraw individual
exports independently. The monochrome variants use the same selected geometry.
Retired logos remain only in the ignored archive and its provenance records.

Reserve clear space of at least 25% of mark height. Start the native symbol at
32px high, full signatures at 200px wide, and standalone Dash at 160px high.
These are initial application minima; inspect the actual consumer size and
review smaller icon use separately. Do not use detailed full-body Dash artwork
as a favicon.

**Mascot lockups.** Compose Dash beside the selected signature while preserving
clear space and hierarchy. Keep full-body artwork separate from the vector
symbol. The developer collection is the default; editorial is an optional
institutional/community application. The [logo board](docs/brand/logo-compositions.html)
and [brand CSS](assets/styles/brand.css) supply applications. The simplified fox
symbol does not replace the character masters or establish hidden anatomy.

**Mascot matrix.** Keep identity stable within the chosen collection. Context
changes through pose, expression, props, and an intentional package emblem.
Shared masters have no chest emblem; compose reviewed symbols separately.
No new eye colors, extra tails, headphones, or silent proportion changes.

| Collection | Master | Role | Supplied poses |
| --- | --- | --- | --- |
| Developer — primary | `dash-developer-welcome` | Package, documentation, default institutional identity | Welcome, reading, guiding, composing |
| Editorial — secondary | `dash-editorial-hoodie` | Welcome, community, softer institutional moments | Welcome, reading, guiding, composing |
| Historical package sources | Cataloged local/public banners | Provenance and costume context | Banner poses, not interchangeable character masters or logo masters |
| Archived sources | Manifest `archived_sources` | Provenance only | Retired face and logo interpretations |

**Documentation.** Use the [documentation pattern](docs/brand/documentation.md)
for navigation, prose, code, callouts, package cards, and responsive reading.
The [standalone stylesheet](assets/styles/documentation.css) adapts the token
export to browser CSS. Generated Tailwind `@theme` needs consumer processing.

**Controls.** Primary buttons have one clear action. Secondary actions use a
solid surface and visible boundary. Focus uses a 2px outline with a 2px offset:
violet on white/light backgrounds and cyan on navy. Aim for 44px touch targets.
Disabled and error states need text or an icon, not color alone.

**Package cards.** Use exact Composer package names, one responsibility, and a
repository link. Do not decorate unavailable features as implemented. Use icons
with text labels. The archived icon set is a reference, including third-party
marks whose usage rights have not been recorded.

**Motion.** Speed trails in supplied assets are still images. Future animation
should be brief, optional, and avoid loops behind reading content. Honor
`prefers-reduced-motion`. The specimen works without animation or remote requests.

## Do's and Don'ts

- Choose one character rendering family per composition; use the catalog status.
- Use real alpha PNGs rather than images with a baked-in checkerboard.
- Keep technical content selectable and understandable without illustration.
- Provide alt text for meaningful images and empty alt text for decoration.
- Use the exact existing lockups; avoid stretching, mirroring, arbitrary colors,
  replacing the fox face, or changing the selected symbol's silhouette.
- Avoid performance superlatives, unverified metrics, fictional product APIs,
  and inherited license claims from mockups.
- Record a new derivative and its source hash; never silently overwrite a
  canonical reference with a newly generated interpretation.

## Reproducible exports

This file follows the [DESIGN.md specification](https://github.com/google-labs-code/design.md).
It is the token source; generated exports live in `assets/tokens/`.
The CLI version is pinned in the [asset guide](assets/README.md). Re-export all
three files together after changing tokens, then validate the asset manifest.
Brand artwork and UI tokens have different jobs. The token exports do not
generate the logo SVGs; those come from the separate vector master and generator.
Font licensing is recorded with its source and does not assign a blanket license
to other supplied illustrations.
