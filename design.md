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
    lineHeight: 1.1
    letterSpacing: "-0.03em"
  h2:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 2rem
    fontWeight: 700
    lineHeight: 1.2
  body-md:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 1rem
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: 0.875rem
    fontWeight: 600
    lineHeight: 1.4
  code:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.6
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
pixel matches those colors. [soul.md](soul.md) defines the character's purpose;
[style.md](style.md) defines the voice. The [asset catalog](assets/README.md)
records the actual reusable files and their provenance.

The identity has three distinct parts: forward chevrons identify the framework,
the orange fox helps people learn and participate, and dark speed trails express
momentum in editorial artwork. Keep everyday documentation calm and readable.

The [brand specimen](docs/brand/index.html) presents the mascot matrix, palette,
and reference families. It is a local review artifact, not a deployed website.

## Colors

| Role | Token | Application |
| --- | --- | --- |
| Interactive emphasis | `primary` / `primary-hover` | Buttons and selected navigation on light surfaces. |
| Forward gradient | `primary` → `blue` → `cyan` | Chevrons and decorative accents; retain the supplied logo artwork. |
| Dark stage | `navy` | Hero, code panels, and illustration backgrounds. |
| Reading surfaces | `neutral`, `white` | Documentation and cards. |
| Text | `ink`, `muted` | Headings/body and supporting copy on light surfaces. |
| Structure | `border` | Quiet separators; not sufficient alone to identify a control. |
| Character shorthand | `fox-orange`, `fox-cream`, `fox-outline` | Swatches for orange fur, cream markings, and warm brown contour. |

Use solid violet with white text for the primary action. Cyan and orange accents
take dark text. A gradient logo is artwork; it is not a reliable text background.
Use a visible outline or filled boundary where control recognition needs it.

Text pairs must meet 4.5:1 for normal text and 3:1 for large text; UI boundaries
and focus indicators need 3:1 against adjacent colors. Color alone must not
communicate a state. These rules follow
[WCAG contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
Token lint covers declared component text pairs, not complete-page accessibility.

## Typography

Use the system sans-serif stack for interfaces and the system monospace stack
for code. This keeps the initial system usable without downloadable font assets
or an unrecorded font license. Raster wordmark lettering belongs to the logo;
do not substitute it as an interface font or claim a known typeface for it.

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
artwork would reduce contrast. Glossy package illustrations can remain in
campaign banners; avoid combining them with flat character drawings in one scene.

## Shapes

Use gently rounded rectangles for cards and code panels. Chevrons travel to the
right. Keep illustrations proportional, preserve transparent padding, and avoid
stretching, mirroring, or clipping the fox's ears and tail.

The fox has large triangular ears with cream inner fur, a short rounded muzzle,
cream cheeks/chest/tail tip, a small dark nose, warm amber/brown eyes, an orange
forehead tuft, dark paws, and a full sweeping tail. The primary neutral reference
is [fox-welcome.png](assets/mascot/fox-welcome.png). Its anatomy and face govern
new neutral poses; the reading references corroborate the same character family.
Use the source image to judge proportions rather than inventing a turnaround
or hidden anatomy from one view.

## Components

**Logo.** Use [wordmark-light.png](assets/brand/wordmark-light.png) on white or
light neutral surfaces. Its dark “PHP” text disappears on navy. On dark surfaces,
use [mark.png](assets/brand/mark.png) plus live white “PHP Fast Forward” text, or
a cataloged self-contained dark banner. A true light-lettered vector lockup is
future work. Do not recolor raster exports with CSS filters.

Reserve clear space of at least 25% of the mark height around a logo. Recommended
initial display minima: 48px high for the mark, 240px wide for a full lockup,
96px for a square avatar, and 160px high for a detailed standalone fox. These
are conservative starting rules, not claims of optical validation at every size.
Below them use text or a simple UI icon. Do not use the detailed mascot as a favicon.

**Mascot matrix.** The anatomy is stable; pose, expression, and optional package
costume express context. Established package artwork uses a purple hoodie and
a white package emblem. Keep it a package costume rather than making it a
requirement for the neutral framework character. Headphones, new eye colors,
and new species/body shapes are not established neutral canon.

| Asset family | Status | Purpose | Consistency rule |
| --- | --- | --- | --- |
| Welcome + heart | Canonical neutral | Onboarding, thanks, community footer | Reference face, cream markings, brown eyes, no clothing. |
| Reading + wave | Canonical neutral | Guides and first steps | Preserve the welcome fox's face and silhouette. |
| Reading + sparkles | Canonical neutral | Concepts and learning | Sparkles/question marks are removable context, not anatomy. |
| Fox + forward chevrons | Canonical lockup | Framework identity and hero | Preserve mark direction, hierarchy, and clear space. |
| Framework package banner | Historical public reference | Existing ecosystem identity | Older flat style; composition and copy are not normative. |
| Dev Tools / Enum banners | Package variants | Tooling and package campaigns | Hoodie/emblem may vary; glossy rendering is its own family. |
| Coffee + books fox | Exploratory | Comparison only | Flatter chibi proportions diverge from the primary reference. |
| Technical fox with headphones | Exploratory | Comparison only | Costume and face diverge; not a new reference master. |

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
  replacing the fox face, or changing the chevron count/direction.
- Avoid performance superlatives, unverified metrics, fictional product APIs,
  and inherited license claims from mockups.
- Record a new derivative and its source hash; never silently overwrite a
  canonical reference with a newly generated interpretation.

## Reproducible exports

This file follows the [DESIGN.md specification](https://github.com/google-labs-code/design.md).
It is the token source; generated exports live in `assets/tokens/`.
The CLI version is pinned in the [asset guide](assets/README.md). Re-export all
three files together after changing tokens, then validate the asset manifest.
Brand artwork and UI tokens have different jobs: exports do not reconstruct
the raster logo, license it, or turn it into a vector master.
