# Publishing the brand library with GitHub Pages

The [Brand Pages workflow](../../.github/workflows/brand-pages.yml) stages this
repository's public visual library and deploys it with the official GitHub Pages
Actions. The [library](index.html), [logo applications](logo-compositions.html),
and [documentation specimen](documentation.html) keep their repository-relative
paths, so they work under a project site's base path.

The repository's configured project-site URL is
`https://php-fast-forward.github.io/.github/`. The deployment job reports the
actual URL through its `github-pages` environment. If the repository uses a
custom domain, use that reported URL instead.

## Repository setup

This repository uses **Settings → Pages → Build and deployment → Source →
GitHub Actions**, with HTTPS enforced. Its `github-pages` environment allows
deployments from the `main` branch. These settings were verified on 2026-10-04;
the first publication still requires the workflow to run from `main`.

When adopting this workflow in another repository, an administrator or
maintainer must enable GitHub Actions as the Pages source and configure its
deployment environment according to that repository's review and deployment
policy.

The workflow reads the existing Pages configuration with `enablement: false`.
It does not enable Pages automatically or require an administration token.
Deployment fails with a setup message if Pages has not been enabled.

See GitHub's documentation for [publishing sources](https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site)
and [custom Pages workflows](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages).

## Build and deployment behavior

- Pull requests validate the brand inventory, native logo exports, design token
  exports and browser adapter, exercise publication boundaries, build the site,
  and upload an artifact. They do not deploy.
- Relevant pushes to `main` build and deploy the library.
- A manual run from **Actions → Brand Pages → Run workflow** deploys only when
  run from `main`. Runs from another branch build without deploying.
- The build job has `contents: read`. Only the deployment job also receives
  `pages: write` and `id-token: write`.
- Actions are pinned to full commit SHAs. Deployment concurrency does not cancel
  an active run. Only `main` uses the shared deployment group; PR and manual
  feature-branch runs have ref-specific groups. Uploaded artifacts expire after
  one day.

## Publication boundary

The [staging script](../../scripts/build-brand-pages.py) copies the HTML, public
guides and receipts in `docs/brand`, approved root guides, the asset guide,
token exports, and cataloged active assets. Canonical, package-variant,
reference support files, and explicitly labeled exploratory artwork retain
their classifications in the filtered Pages asset manifest.

Exploratory assets awaiting review are excluded unless their catalog records
explicitly authorize `brand-review-gallery` display through a
`maintainer-request`. The five developer scene proposals have this separate
authorization following the request to publish these asset-visualization pages;
they remain labeled proposals and are not promoted to canonical references.

The two cataloged profile applications, `brand-hero-banner.png` and
`docs-installation.png`, retain their `profile/assets` paths. Public font
sources, their license and metadata, and the logo build script are available
when present. The multilingual profile sources are included for guide links.
Guides remain readable Markdown source files; this workflow adds no Markdown
renderer or application runtime.

The eight selected logo exports and both profile applications explicitly record
the maintainer's authorization for `brand-public-library` publication. Their
earlier `local-not-published` state remains historical metadata; authorization
does not claim that a deployment has occurred. Records still carrying the
current `local-not-published` state are excluded, even when canonical.

When `publication` is present, the builder accepts only an authorization record
with `maintainer-request`, nonempty `recorded_at` and `evidence`, and a recognized
scope. `brand-public-library` also requires `status: authorized`.
`brand-review-gallery` applies only to exploratory, awaiting-review proposals
with `canonical_selection: false`. Unknown strings, scopes, incomplete records
and other value types are excluded. Absent metadata retains the existing active
asset policy; an absent record never authorizes an awaiting-review proposal.

Resources loaded by the HTML specimens must belong to the selected local kit.
External image, script and stylesheet URLs block the build; external navigation
links remain available. In the staged Markdown sources, remote badge images
become their text labels inside the existing links, and other remote Markdown
images become explicit links. The repository READMEs keep their original badges.

The site does not copy `backup/`, `references/`, private directories, historical
assets marked `legacy`, uncataloged artwork, or the untracked social preview.
Reference images in the local specimen become explicit repository links in
the staged copy, rather than automatically loaded images. Files published in
the library keep relative links; other public source links point to the exact
GitHub commit used by the build.

In the filtered manifest, `provenance.source_ids` resolves only to IDs in its
`assets` or `archived_sources` registry. IDs whose source records remain outside
the site move to `provenance.repository_source_ids`, with
`provenance.repository_manifest` pointing to the complete repository manifest at
the build's source ref. This preserves every source ID without publishing the
reference files. Source hashes, dates, and rights metadata remain unchanged;
the repository manifest remains the complete asset record.

Before upload, the builder checks asset hashes, symlink boundaries, local media,
HTML links, and HTML anchors. It refuses to overwrite a nonempty destination.
The brand validator remains the source inventory check; building the site does
not change asset approval or licensing status.

## Preview the staged site locally

From the repository root, with Python 3 available:

```sh
python3 scripts/validate-brand.py
python3 scripts/test-brand-pages.py
python3 scripts/build-brand-pages.py --output-dir _site
python3 -m http.server 8765 --bind 127.0.0.1 --directory _site
```

Open `http://127.0.0.1:8765/`. The root page redirects to
`docs/brand/index.html`. For another build, choose a new empty destination;
the script deliberately leaves an existing output directory untouched.

Inspect the staged artifact before adopting workflow changes. A successful
local build confirms files and links; the Actions deployment result confirms
publication on GitHub Pages.
