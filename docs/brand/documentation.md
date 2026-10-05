# PHP Fast Forward documentation pattern

The [executable reference](documentation.html) defines the documentation surface:
a navy navigation rail, a calm reading column, selectable code panels, and a
secondary table of contents. It evolves the historical documentation concept
using the current fox signature and selected Dash artwork. Archived concept
content, requirements, and APIs are not product specifications.

The design repository owns this pattern, [documentation.css](../../assets/styles/documentation.css),
the [visual tokens](../../DESIGN.md), and cataloged artwork. Package repositories
own their documentation, release requirements, examples, API behavior, and
publication. The application or documentation generator owns routes, search,
content rendering, and runtime integration.

## Consume the style

The reference is static HTML with optional inline JavaScript. Open it directly
in a browser, or serve the repository with a local static server. It needs no
dependency installation, build, remote font, or automatic network request.
External links navigate only when activated.

Copy the stylesheet and the selected assets with their manifest IDs, source
commit, hashes, and applicable provenance. Load the CSS once and put the
documentation surface inside `.ff-docs`. All component selectors are scoped to
that wrapper. Shared token names are global; define an explicit precedence when
combining this pattern with another design system.

```html
<link rel="stylesheet" href="/assets/styles/documentation.css">
<div class="ff-docs" data-reading-theme="light">
  <!-- Navigation and semantic documentation content. -->
</div>
```

The reference defaults to a light reading surface. A compact sun/moon button
provides the theme action with an accessible label. `data-reading-theme="navy"`
switches the reading palette; the navigation and code surfaces remain navy.
The optional toggle uses `aria-pressed`. It does not change a consumer's theme
preferences or impose persistence.

## Token adapter

`assets/tokens/theme.css` is a generated **Tailwind v4 `@theme` export**, not a
standalone browser token stylesheet. A Tailwind consumer processes that export
in its own build. The browser reference instead ships a small `:root` adapter
inside the `ff-token-adapter` CSS layer, followed by the `ff-documentation` layer.
It preserves the exported `--color-*`, `--font-*`, `--text-*`, `--radius-*`, and
`--spacing-*` names and values, including the associated `--text-*--line-height`
metadata. The export helper normalizes the upstream CLI's font lists before
writing reusable tokens, so both consumers receive the same fallback order.

When tokens change, run `php scripts/export-design.php --tokens-only` first.
This explicit preparation mode writes normalized exports without checking the
browser adapter, which still contains the previous values. Update this
adapter from `theme.css`, and review the normalization of font stacks. Compare
every adapted declaration with the export, then inspect both reading palettes.
Do not hand-edit generated exports to make this reference render.
Refresh the stylesheet's byte count and digest in the asset manifest, then run
the asset validator. CI always uses the complete `--check` mode.

## Components

| Component | Contract and classes | Behavior |
| --- | --- | --- |
| Page shell | `.ff-docs`, `.ff-docs-shell`, `.ff-docs-content` | Centered, maximum 1200px width. One `main` landmark and one page H1. |
| Skip link | `.ff-docs-skip`, target `#main` | First focusable link; target can receive focus. |
| Navigation rail | `.ff-docs-rail`, `.ff-docs-navigation`, `.ff-docs-nav-link` | Semantic `nav` inside native `details`/`summary`. `aria-current="page"` marks the actual page. |
| Breadcrumb | `.ff-docs-breadcrumb` | A labelled `nav` with an ordered list; the last item identifies the page. |
| Heading and prose | `.ff-docs-article`, `.ff-docs-kicker`, `.ff-docs-lead`, `.ff-docs-page-meta` | Sentence case, about 70 characters per reading line, system fonts, meaningful heading order. |
| Code panel | `.ff-docs-code`, `.ff-docs-code-header`, `pre > code` | Selectable, horizontally scrollable text. The scroll area receives keyboard focus. No shell prompt or decoration inside copyable code. |
| Copy control | `.ff-docs-copy`, `.ff-docs-copy-status` | Optional enhancement: hidden without JS, copies `textContent`, announces results through `role="status"`. Clipboard failure selects code for manual copying. |
| Callout | `.ff-docs-callout`, optional `data-kind="important"` | A short named note with real text. Important notes have a complete visible boundary. Ordinary notes are not live announcements. |
| Package card | `.ff-docs-package`, `.ff-docs-package-name` | Exact Composer package name, one responsibility, and a real destination. No unverified badge, metric, or availability claim. |
| Page contents | `.ff-docs-toc` | Labelled anchor navigation to real section IDs. `aria-current="location"` follows the URL fragment, not an invented scroll position. |
| Learning companion | `.ff-docs-companion` | The principal developer family of Dash, original proportions, and brief supporting text. Keep the secondary softer family in its own compositions. The mascot does not carry an instruction or replace the page title. |
| Page footer | `.ff-docs-page-footer` | Useful next-step or source links, with meaningful labels. |

## Responsive and accessible behavior

At wide sizes, navigation, prose, and the secondary column form three zones.
Below 1080px, the secondary column follows the article. Below 760px, the rail
becomes a compact header with a native navigation disclosure. The reference
enhancement initially collapses it on narrow screens and keeps it open on wide
screens. Without JavaScript, the native disclosure remains open and operable.

Use controls at least 44px high. Preserve visible keyboard focus: violet on
light reading surfaces and cyan on navy. Keep the reading column first in the
article/aside source order. Retain text labels alongside any visual state.
Do not add mascot animation behind prose; the reference has no animation and
includes a reduced-motion rule for consumers that add motion.

The copy enhancement announces success or a manual fallback. On mobile, choosing
an in-page navigation link closes the disclosure and focuses its real target.
Escape closes an open mobile disclosure and returns focus to its summary. Do
not convert this disclosure into a modal without implementing modal semantics.

Review keyboard order, fragment links, both palettes, enlarged text, narrow
viewports, no-JS navigation, code selection, and clipboard failure. Text and
focus contrast must follow `DESIGN.md`; a good token pair alone does not prove
the complete page meets accessibility requirements.

## Framework adapters

| Consumer | Mapping | Ownership |
| --- | --- | --- |
| Plain HTML or a static documentation generator | Render these semantic elements and `ff-docs-*` classes; replace example URLs with real routes. | Consumer owns templates, search, route generation, and release content. |
| Bootstrap | Keep the scoped shell and reading classes. Map tokens to Bootstrap variables in the consumer's theme; use a local adapter for control semantics rather than stacking conflicting utility rules. | Consumer chooses Bootstrap version and integration. This pattern imports none. |
| React | Map `class` to `className`; render breadcrumbs/navigation from data, keep code text as a string, and implement copy/theme/disclosure state in the application's components. | Consumer owns state and cleanup of listeners. Do not inject the specimen's inline script into React. |
| Tailwind | Process the cataloged `@theme` export, then apply or translate the component rules. Resolve token layer precedence once. | Consumer owns Tailwind/build tooling; this reference requires neither. |

Keep framework-specific adapters in the consuming repository until a reusable,
versioned adapter is deliberately added to this design system. Changing this
pattern does not deploy a documentation site or modify a package runtime.

## Example provenance and validation

The installation command, PHP minimum, and bootstrap example were checked against
`php-fast-forward/framework` at commit
[`dac7ef8e4cfd8d39f36d526a6a4345958bcc83eb`](https://github.com/php-fast-forward/framework/tree/dac7ef8e4cfd8d39f36d526a6a4345958bcc83eb),
using its [README](https://github.com/php-fast-forward/framework/blob/dac7ef8e4cfd8d39f36d526a6a4345958bcc83eb/README.md),
`composer.json`, and `FrameworkServiceProvider` source. They were source-verified;
this reference does not execute the PHP application or install its dependencies.
Recheck the consumer's target release before publishing the examples there.

The stylesheet and inline enhancement were authored for this repository. The
reference uses local `fast-forward-logo-dark.svg` and `dash-developer-reading.png`; Dash is
the Fast Forward fox. Artwork status and reuse rights remain governed by the
[asset catalog](../../assets/README.md). Root asset validation and final browser
review belong to the integrated delivery, including its links and manifest.
