# Production prompt and receipt

Use this reference after resolving the local kit and one collection. The
inputs below are task variables to fill from the brief and inspected masters.
They are not instructions to guess missing source files or hashes.

## Prompt assembly

Write a complete prompt containing these six parts:

1. **Identity references:** identify the attached primary as the face/anatomy
   authority; identify any same-collection pose reference separately.
2. **Purpose and action:** describe what the reader should understand and the
   concrete pose or interaction that supports it.
3. **Collection:** preserve the developer master's mature proportions and
   polished 3D shading, or the explicitly selected editorial master's cute
   2D drawing. Preserve the purple hoodie with a plain chest, amber/brown eyes,
   orange/cream markings, dark nose, ears, tuft, and one complete tail.
4. **Composition:** specify crop, negative space, intended size, surface, and
   minimal props. Keep the character complete unless the brief deliberately
   calls for a reviewed crop.
5. **Delivery:** request PNG for a raster illustration, with real alpha for a
   cutout or the specified scene background.
6. **Excluded content:** no generated text, code, letters, package logos,
   watermarks, invented interfaces, extra limbs/tails, altered eye color, or
   mixing with the other collection. Compose reviewed text and logos afterward.

Example prompt for a developer documentation guide:

> Use the attached Dash developer welcome master as the identity authority and
> the attached developer guide pose as movement guidance. Create Dash calmly
> presenting an open paw toward empty space on the right, helping a PHP
> developer find the next section of a documentation page. Preserve the
> master's mature face, amber/brown eyes, orange fur, cream muzzle and cheeks,
> triangular ears, tuft, dark nose, single cream-tipped tail, purple hoodie,
> and polished 3D shading. Keep the chest plain. Include no props. Keep the
> entire character inside the canvas with generous padding. Deliver a PNG
> cutout with real transparent background, suitable for light and navy pages.
> No text, code, logos, watermarks, new anatomy, or cute editorial proportions.

Only attach the optional guide image if its file and hash were resolved. If it
is absent, adapt the prompt to derive the open-paw pose from the primary alone.

## Receipt fields

| Field | Record |
| --- | --- |
| `brief` | Audience, purpose, collection, package, pose, props, surface, text surface, output dimensions/format and requested status. |
| `sources` | Each actual reference's manifest ID, role, repository-relative path, SHA-256 and source commit when known; local/untracked provenance remains explicit. |
| `prompt` | Exact final text submitted; no shortened paraphrase. |
| `generator` | Capability/provider/model/version only when exposed; use `null` for unknown values. |
| `transparency` | Requested background, actual alpha availability, transparent-region/edge inspection and light/navy checks. |
| `output` | Actual path, format, dimensions, byte length and SHA-256, or `null` before generation. |
| `edits` | Transformations or separately composed logos/text with their own source evidence. |
| `validation` | Structural result, likeness result, remaining defects, review status and catalog state, each recorded separately. |

Use `ready-not-generated` while only the brief and prompt exist. After tool
output, record `generated-unreviewed`; after inspection, record what passed and
what failed. A visually accepted candidate can be cataloged under the central
kit's current status rules. Do not invent an output hash, model version,
publication URL, review approval, or missing reference digest.

The example briefs pin existing evidence and name their required family master.
Before generation, recheck their pinned hashes and resolve the required master
through the current manifest. Record that master's actual hash in the receipt;
the fixture does not claim a generated master exists in every kit revision.
