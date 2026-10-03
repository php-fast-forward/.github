# Producing consistent Dash artwork

Read [DESIGN.md](../../DESIGN.md), [SOUL.md](../../SOUL.md), [STYLE.md](../../STYLE.md),
and the [pose matrix](index.html#matrix). Use the versioned
[dash-art skill](../../skills/dash-art/SKILL.md) for a new repository illustration.
The [manifest](../../assets/manifest.json) resolves each reference to actual bytes.

## Reference hierarchy

| Priority | Manifest ID | What it establishes |
| --- | --- | --- |
| Primary developer master | `dash-developer-welcome` | Default face, upright proportions, dimensional fur/cloth, hoodie, one tail. |
| Same-collection support | `dash-developer-reading`, `dash-developer-guide`, `dash-developer-build` | Learning, presenting, and composing poses with the developer identity. |
| Secondary editorial master | `dash-editorial-hoodie` | Newly authored cute face, compact proportions, painted contours and hoodie. |
| Same-collection support | `dash-editorial-reading`, `dash-editorial-guide`, `dash-editorial-build` | Editorial learning, presenting, and composing poses. |
| Costume/context evidence | `reference-package-event-dispatcher`, `reference-package-fork`, `reference-package-dev-tools` | Established developer banner family; their text is not an API specification. |
| Historical comparison | `reference-package-enum`, `reference-package-framework`, legacy profile URLs | Recorded older interpretations; not substitutes for the current masters. |

**Developer is the default.** Editorial is a deliberate secondary choice for
welcoming, community, or a softer institutional application. Supply the actual
master for the selected collection; a text description alone is insufficient.
Keep that collection's face, proportions, and rendering together. The original
cute source-pack exports are archived in the ignored local backup.

## Delivered pose matrix

| Context | Pose / prop | Developer | Editorial |
| --- | --- | --- | --- |
| Welcome / appreciation | Open-paw greeting | [Welcome](../../assets/mascot/dash-developer-welcome.png) | [Welcome](../../assets/mascot/dash-editorial-hoodie.png) |
| Documentation / learning | Plain open book | [Reading](../../assets/mascot/dash-developer-reading.png) | [Reading](../../assets/mascot/dash-editorial-reading.png) |
| Explanation / next step | Open-paw guiding gesture | [Guiding](../../assets/mascot/dash-developer-guide.png) | [Guiding](../../assets/mascot/dash-editorial-guide.png) |
| Composition / building | Two plain connected blocks | [Composing](../../assets/mascot/dash-developer-build.png) | [Composing](../../assets/mascot/dash-editorial-build.png) |

These eight images are selected reference poses with real alpha. They are not a
front/side/back orthographic model sheet. New hidden anatomy remains authored
interpretation requiring visual review.

## Brief and production

Specify audience, purpose, collection, package, pose, minimal props, background,
text space, format and destination. Use the matching master plus a relevant
same-collection pose. Preserve amber/brown eyes, orange and cream markings,
triangular ears, nose, paws, exactly one cream-tipped tail, and the purple hoodie
with two cream drawstrings. Keep the shared chest plain; separately compose a
reviewed package emblem when needed. The skill's
[prompt/receipt template](../../skills/dash-art/references/prompt-and-receipt.md)
contains the reusable production fields.

Use the image capability for raster illustration. Add exact titles, commands,
code and marks through deterministic composition afterward. Reserve clean
space for this text; do not use banner screenshots as evidence for product APIs.
New filenames and recorded sources preserve the identity chain.

For a cutout, inspect actual transparent pixels and the silhouette on light and
navy. Hidden RGB values in alpha-zero pixels do not form a visible background;
look for visible edge haze, clipping or a painted checkerboard in the rendered
result. Compare face, markings, eyes, hoodie, limbs, and tail at the intended
size. A file-integrity pass cannot establish likeness or licensing.

The [generation receipts](dash-generation.json) contain the exact prompts,
source hashes, tool information exposed, edits, and output identities used for
this collection. Built-in image generation produced the raster files; no model
identifier was asserted when the tool did not expose it.

## Next reference work

A deliberate future sheet can add expressions and orthographic front/profile/
back views per collection. Keep the developer and editorial sheets separate and
review each new view before promoting it to the kit. Record rights, source/tool
information and any separately composed font or symbol. Logo lettering masters
and broader source-rights work remain tracked in issue #8.
