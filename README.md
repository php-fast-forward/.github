<p align="center">
  <img src="./profile/assets/mascot-wordmark-horizontal.svg" alt="PHP Fast Forward" width="760">
</p>

# PHP Fast Forward Organization Profile

This repository holds the public GitHub organization profile and shared GitHub
metadata for [`php-fast-forward`](https://github.com/php-fast-forward).

It exists so the organization page can have a proper manifesto, visual identity,
shared automation, and sponsorship metadata without mixing that content into the
framework or tooling repositories themselves.

## What lives here

- [`profile/README.md`](./profile/README.md) is the organization profile shown on
  the GitHub organization page
- [`profile/assets`](./profile/assets) contains the public assets used by that
  profile
- [`.github/FUNDING.yml`](./.github/FUNDING.yml) defines the organization
  sponsorship links
- [`.github/workflows`](./.github/workflows) contains reusable GitHub Actions
  workflows shared by Fast Forward repositories
- [`.github/actions`](./.github/actions) contains composite actions used by the
  shared workflows
- [`docs/github-actions-inventory.md`](./docs/github-actions-inventory.md)
  records which workflow and action surfaces moved here first

## Notes

- Private or spoiler assets can be kept outside versioned profile assets
- The organization manifesto itself lives in [`profile/README.md`](./profile/README.md)
- Framework code and developer tooling live in their own repositories, especially
  [`framework`](https://github.com/php-fast-forward/framework) and
  [`dev-tools`](https://github.com/php-fast-forward/dev-tools)

## Shared automation

The organization automation split follows
[`php-fast-forward/dev-tools#240`](https://github.com/php-fast-forward/dev-tools/issues/240):

- this repository owns reusable workflows and composite GitHub Actions
- `dev-tools` owns the PHP CLI commands, Composer plugin, and consumer workflow
  wrapper synchronization
- consumer repositories keep thin workflow files that call reusable workflows
  from this repository

Reusable workflows checkout this repository into `.fast-forward-actions` before
calling local composite actions. Workflows that need the Fast Forward CLI use
`.github/actions/dev-tools/setup`, which prefers an existing project-local
`vendor/bin/dev-tools` binary and otherwise installs `fast-forward/dev-tools`
globally through Composer. The setup action accepts a `version` input so wrapper
workflows can test a specific `dev-tools` branch or version without requiring
the consumer package to depend on `fast-forward/dev-tools`.

The first extraction keeps wrappers in `dev-tools` until the coordinated
consumer-facing wrapper update is ready. Release-safe references for those
wrappers remain tracked separately in
[`php-fast-forward/dev-tools#238`](https://github.com/php-fast-forward/dev-tools/issues/238).
