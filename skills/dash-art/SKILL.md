---
name: dash-art
description: Create or adapt PHP Fast Forward repository artwork featuring Dash, using verified character masters, a focused illustration brief, and recorded asset provenance.
---

# Dash artwork

Produce recognizable artwork of **Dash, the Fast Forward fox** for repository
headers, documentation, package illustrations, and community surfaces. Use the
mature developer collection by default. The newly created cute editorial
collection is a secondary choice when the user or brief explicitly requests it.

## Resolve the local brand kit

Use `DASH_BRAND_ROOT` when provided. Otherwise inspect the ancestors of the
current workspace and this skill for a root containing `DESIGN.md` and
`assets/manifest.json`; confirm it is the PHP Fast Forward kit. Read its
`DESIGN.md`, `SOUL.md`, `STYLE.md`, and asset guide before choosing references.
Follow the current kit's rules rather than keeping a private copy of its tokens.

If the kit cannot be accessed, request its local root or attachments of the
current family master and relevant guidelines. Do not download a kit, install
this skill, search a hardcoded home directory, or invent Dash from a text-only
description. If the image capability is unavailable, deliver the completed
brief, reference receipt, and prompt with status `ready-not-generated`.

## Select one collection

| Collection | Primary manifest ID | Supporting pose IDs | Use |
| --- | --- | --- | --- |
| `developer` — default | `dash-developer-welcome` | `dash-developer-guide`, `dash-developer-reading`, `dash-developer-build` | Mature, composed guide with polished 3D shading and the purple hoodie. |
| `editorial` — explicit secondary | `dash-editorial-hoodie` | `dash-editorial-guide`, `dash-editorial-reading`, `dash-editorial-build` | Newly created cute 2D character, warm outline, compact proportions, purple hoodie. |

Resolve IDs through the manifest, verify each selected file's SHA-256, and view
the actual images. Use the primary plus an available relevant pose; a supporting
pose does not replace the primary face reference. An absent optional pose can
be derived from the primary. An absent or mismatched primary requires corrected
references before generation. Original cute artwork in `backup/` is historical
and must never become an automatic fallback; consult it only for an explicit
request to revisit that archive.

Keep the selected collection's face, proportions, paws, and finish together in
each composition. Both collections preserve orange fur, cream muzzle/cheeks and
tail tip, warm amber/brown eyes, triangular ears, the forehead tuft, small dark
nose, **one** complete fluffy tail, and a friendly expression. Use the master
to judge anatomy; do not infer an unseen turnaround. Do not blend editorial eyes
or head proportions with developer rendering. Violet eyes in historical `enum`
art and duplicated tails in old generated reports are comparison evidence,
not instructions for new Dash artwork.

## Brief and generate

Record audience, purpose, collection, exact package or framework scope, pose,
props, background/transparency, destination surface, text treatment, output
format/dimensions, and requested status. Infer routine choices from the request;
ask only when an unresolved choice would change the result materially. Read
[the prompt and receipt template](references/prompt-and-receipt.md) when preparing
production. [The example briefs](references/example-briefs.json) show concrete
requests; they are ready briefs, not claims that images have been generated.

Supply the selected actual masters to the host's available image-generation or
editing capability. State which image controls identity and which supports a
pose. Match the chosen collection and preserve all ears, paws, and the tail
inside the canvas. For cutouts, request real alpha transparency; for scenes,
specify the intended surface and clean space for editable text.

Keep the hoodie chest plain unless a separately composed, reviewed existing
logo/emblem is part of the brief. Never ask the model to invent a package mark.
Add exact titles, commands, code, versions, and logos through deterministic
composition after illustration. Package banners can inform props and atmosphere;
their rendered code, APIs, PSR claims, and metrics are not software evidence.
Verify such copy against the actual package documentation before composing it.
Deliver generated raster art as raster art; an SVG wrapper containing a PNG is
not a new vector master.

## Inspect and deliver

Inspect likeness, eye color, single-tail anatomy, limbs, silhouette, complete
edges, unintended letters/logos, and the collection's finish. Check cutouts on
light and navy surfaces: an alpha channel alone does not prove transparency,
and a painted checkerboard is not a transparent background. Inspect at the
intended display size and provide suitable alt text, or empty alt text when
the surrounding surface makes the image decorative.

Record the exact prompt, source IDs/paths/hashes, known source commit, tool/model
when exposed, edits, output hash/dimensions/transparency, and the checks actually
performed. Keep generation, visual review, and cataloging as separate states.
New work stays `exploratory` until selected through the current task's review;
do not replace a master or historical URL silently.

When adding the result to the central kit within the authorized task, give it a
new filename, record its manifest provenance, and run the kit's documented asset
validator. Its structural pass proves file integrity, not likeness or licensing.
When delivering to another repository, preserve source commit/hash evidence
with the copied file. Publication, installation, and licensing follow the
applicable repository contract and the user's authorization; generating a
candidate does not perform those actions.
