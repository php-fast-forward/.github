---
name: fast-forward-dash-art
description: Create contextual PHP Fast Forward repository illustrations featuring Dash. Use verified developer or editorial masters, a concrete repository brief, native image generation, and recorded provenance instead of reusing a generic mascot banner.
license: MIT; instructions and examples only. See LICENSE.
compatibility: Supports php-fast-forward/brand-kit ^0.3. Requires a reviewed local brand kit with DESIGN.md and assets/manifest.json and a host image-generation capability. The optional reference-resolution example requires PHP 8.4+. Installing this skill does not install the artwork library or an image generator.
metadata:
  author: PHP Fast Forward
  source: original
  repository: https://github.com/php-fast-forward/.github
  api-baseline: php-fast-forward/brand-kit ^0.3
---

# Contextual Dash artwork

Create an image **with Dash that represents the actual repository**. Read its
README/source to understand the responsibility before choosing a pose, props or
metaphor. For an event dispatcher, one source reaching several listeners is a
useful composition; it is not a license to invent an API. Keep exact code,
titles and reviewed marks as separately composed text/vector elements.

## First working route

Use a reviewed brand kit from `php-fast-forward/brand-kit ^0.3`. Inspect the
installed Composer version or the checkout's reviewed release/ref; earlier
versions are outside this skill's contract. Set `DASH_BRAND_ROOT` to that
installation/checkout. Confirm its Composer name and read `DESIGN.md`,
`SOUL.md`, `STYLE.md`, `assets/README.md`, and the current manifest. Read the
consumer repository instructions and preserve existing work.

The skill itself is installed separately. `DASH_SKILL_ROOT` below means the
directory containing this `SKILL.md`; do not assume a specific host home path.

1. Create `dash-brief.json` in your working area. This complete starting brief
   describes a contextual header, not a reusable generic fox:

   ```json
   {
     "family": "developer",
     "required_master_id": "dash-developer-welcome",
     "preferred_pose_id": "dash-developer-build",
     "audience": "PHP developers evaluating the event dispatcher",
     "purpose": "Show one event reaching several independent listeners",
     "package": "fast-forward/event-dispatcher",
     "pose": "Dash arranging one source block and three recipient blocks",
     "props": ["one source block", "three recipient blocks", "connection lines"],
     "surface": "repository header on navy with an empty title area",
     "text_surface": "separately composed editable title; no generated code",
     "output": {
       "format": "png",
       "width": 1536,
       "height": 1024,
       "transparent_background": false
     }
   }
   ```

2. Resolve and verify actual reference bytes before generation:

   ```sh
   php "$DASH_SKILL_ROOT/examples/resolve-references.php" "$DASH_BRAND_ROOT" ./dash-brief.json
   ```

   Expect exit 0 and JSON with `status: ready-not-generated`, the developer
   welcome/build IDs, their kit-relative paths and verified SHA-256 digests.
   The [example guide](examples/resolve-references.md) explains failures.
   This read-only helper generates no image and installs nothing. It needs no
   Composer autoloader because it reads the kit's public JSON/files only.

3. Open the actual resolved PNGs. Supply the welcome master as the **identity**
   reference and the build pose as **movement** support to the host's image
   tool. Explain the verified repository responsibility, exact action, props,
   empty text area, family invariants and requested background from the brief.
   The [prompt and receipt template](references/prompt-and-receipt.md) provides
   the full prompt structure and output record.

4. Generate a PNG using the host's image capability. If that capability is
   unavailable, return the completed brief/prompt with `ready-not-generated`;
   do not fabricate an output or substitute an unrelated fox.

5. Inspect face, eye color, ears, paws, single-tail anatomy, hoodie, edges and
   composition. For cutouts inspect real transparent pixels and light/navy
   rendering, not only an alpha-channel flag. Record the **actual** output
   dimensions/hash and exposed tool details. Generated results are not
   automatically approved masters.

6. For a consumer README banner, use `docs/_static/mascot-banner.png` unless
   its repository specifies another path. Preserve the current image until the
   replacement is reviewed; when replacing, remove the obsolete image and fix
   all references. Use descriptive alt text and keep executable instructions
   as selectable text. Keep production records in the central kit/task evidence
   unless the consumer requests them; do not add an assets README or generation
   manifest to every PHP library.

## Keep one collection consistent

| Collection | Identity master | Pose support | Use |
| --- | --- | --- | --- |
| Developer — default | `dash-developer-welcome` | `dash-developer-reading`, `dash-developer-guide`, `dash-developer-build` | Mature package/documentation/default institutional scenes |
| Editorial — deliberate secondary choice | `dash-editorial-hoodie` | `dash-editorial-reading`, `dash-editorial-guide`, `dash-editorial-build` | Softer welcome/community moments |

Keep orange fur, cream cheeks/muzzle and tail tip, warm amber/brown eyes,
triangular brown-rimmed ears, forehead tuft, small dark nose, brown paws and
exactly one tail. The violet hoodie has two cream drawstrings and a plain
chest. Do not blend editorial eyes/proportions with developer 3D rendering.
Historical package banners provide context, not replacement masters.

New model/expression sheets are reviewed proposals. Side/back anatomy and
scene occlusion do not silently redefine the selected masters. If a required
master is missing, has mismatched bytes or belongs to the wrong family, stop
reference resolution and correct the input. Do not fall back to an archive,
search a hardcoded home folder or download/install a kit without authority.

## Prompt, rights and delivery boundaries

Do not generate text, code, new package emblems, performance claims or product
interfaces in the raster. Compose verified labels/logos separately. Prefer a
clear repository metaphor and restrained props; a generic welcome pose alone
does not communicate every package.

Use the [additional example briefs](references/example-briefs.json) for
documentation, event-dispatcher and workstation variants. They are brief inputs,
not evidence of generated assets. Re-resolve current references and record
their actual hashes before use.

Keep generation, visual review, cataloging, maintainer selection and publication
as distinct states. Record exact prompt, input IDs/paths/hashes, reviewed source
revision, edits and actual output metadata. Unknown model/version stays unknown.
Reference the kit's artwork-rights record; this skill's MIT license covers its
instructions/examples and grants no new rights over images, logos or fonts.

Adding a central-kit asset requires a new filename, manifest entry and the
kit's documented PHP checks. Installing instructions does not install a runtime,
authorize global changes or publish to another repository. Honor authority
already given in the current task; do not add repeated confirmation rituals.
