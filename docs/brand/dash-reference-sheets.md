# Dash reference sheets

These sheets complete the authored model/expression proposals requested in
[issue 8](https://github.com/php-fast-forward/.github/issues/8). The developer
and editorial collections stay separate. The selected welcome references remain
the identity masters until the maintainer deliberately accepts a change.

## Models and expressions

| Family | Turnaround | Expressions |
| --- | --- | --- |
| Developer — primary | [Front, left profile, back, oblique front](../../assets/mascot/dash-developer-turnaround.png) | [Six expression studies](../../assets/mascot/dash-developer-expressions.png) |
| Editorial — secondary | [Front, left profile, back, near-frontal oblique](../../assets/mascot/dash-editorial-turnaround.png) | [Six expression studies](../../assets/mascot/dash-editorial-expressions.png) |

View them together in the [reference board](dash-references.html). Full-body
views use one scale/baseline per sheet and preserve one tail, orange/cream
markings, warm brown eyes, brown paws and a plain violet hoodie. The hood rests
on the back; the two cream drawstrings stay on the front. Sheet backgrounds
are intentionally opaque warm off-white, not transparent cutouts.

The expression grids read left to right, top to bottom: calm, delighted,
focused, curious, uncertain, reassuring. The editorial reassuring expression
uses closed smiling eyes.

**New side/back anatomy is authored interpretation.** These are visual reference
proposals, not recovered hidden anatomy, measured orthographic engineering
drawings, animation rigs or silently promoted replacement masters. The final
oblique views are close to front, particularly for editorial; front/profile/back
are the useful directional comparison. Maintainer review of the PR is the
selection gate. Exact prompts, inputs, hashes and inspection limits are in
[dash-model-generation.json](dash-model-generation.json).

## Review of the existing scene proposals — 2026-10-20

Compared against the developer welcome and reading masters:

| Proposal | Visual finding | Decision |
| --- | --- | --- |
| Coding | Warm eyes, dimensional fur/hoodie, two paws at a laptop and one tail. Desk/chair obscure body construction. | Useful contextual scene; keep separate from anatomy masters. |
| Debugging | Master-like face/ears/markings; one paw holds a magnifier, one rests on the table. Props obscure one facial area and the torso. | Useful diagnosis scene; no anatomy promotion. |
| Explaining | Recognizable face, hoodie, one tail and open-paw gesture. Board has decorative diagram blocks. | Useful teaching scene; blocks are not a framework API diagram. |
| Portrait study | Face and hoodie follow developer styling; deliberate upper-body crop excludes the tail/lower body. | Portrait illustration only; neither a complete-body master nor the vector logo. |
| Workstation | One character/tail, warm eyes, violet hoodie, dense equipment scene. Chair, desk and arms obscure anatomy. | Scene reference for setup density; no anatomy promotion. |

This records an agent's visual review, not maintainer selection. Existing
`awaiting-review` catalog states remain accurate until that selection. Keep
text, code and reviewed logos as separate composition elements.

## Small icons

The [reference board](dash-references.html#icons) shows the dedicated exports at
their actual CSS dimensions on light and navy surrounding surfaces. They use
the selected fox **symbol**, not a crop of detailed full-body Dash.

- Favicon: [SVG](../../assets/icons/favicon.svg), [16px](../../assets/icons/favicon-16.png),
  [32px](../../assets/icons/favicon-32.png), [48px](../../assets/icons/favicon-48.png).
- Avatar: [SVG](../../assets/icons/dash-avatar.svg), [64px](../../assets/icons/dash-avatar-64.png),
  [128px](../../assets/icons/dash-avatar-128.png), [256px](../../assets/icons/dash-avatar-256.png),
  [512px](../../assets/icons/dash-avatar-512.png).

A navy rounded backing is part of the icon. At 16px, recognize the orange fox
silhouette and ears; facial details collapse. Use it only for browser-favicon
contexts. At 32px, the muzzle/ear separation is readable. Avatars retain more
clear space. Keep the full signature at its existing 200px minimum; these
exports do not lower that rule or change the eight signature files.

Run `php scripts/build-brand-icons.php --raster` to reproduce both vectors and
PNGs; raster authoring requires `rsvg-convert` (librsvg). The SVG generator/check
uses PHP only; CI does not need librsvg. Run `composer check` for the committed
vector, PNG metadata/source binding and manifest checks. Renderer/version,
output hashes, actual-size findings and limits are in
[icon-validation.json](icon-validation.json).

## Authorship and rights

The maintainer confirmed on 2026-10-20 that all original Dash illustrations
were generated with generative AI. This establishes the supplied authoring
method, not an undisclosed model/version or a new blanket license. Read the
[artwork rights record](artwork-rights.md) before reuse.

