# PHP Fast Forward

<p align="center">
  <strong>English</strong> · <a href="README.pt-BR.md" lang="pt-BR">Português (Brasil)</a> · <a href="README.es.md" lang="es">Español</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="../assets/brand/fast-forward-logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="../assets/brand/fast-forward-logo.svg">
    <img src="../assets/brand/fast-forward-logo.svg" alt="PHP Fast Forward logo" width="640">
  </picture>
</p>

<p align="center">
  <strong>The PHP framework for developers who want speed without surrendering architecture.</strong>
</p>

<p align="center">
  PSR-first • composable • event-driven • human + agent workflows
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/framework"><img src="https://img.shields.io/badge/Framework-core-1E293B?logo=github&logoColor=white" alt="Framework repository"></a>
  <a href="https://github.com/php-fast-forward/dev-tools"><img src="https://img.shields.io/badge/Dev%20Tools-agentic%20tooling-F28D1A?logo=github&logoColor=white" alt="Dev Tools repository"></a>
  <a href="https://github.com/php-fast-forward/http"><img src="https://img.shields.io/badge/HTTP-PSR--7%2F17%2F18-0F766E?logo=github&logoColor=white" alt="HTTP repository"></a>
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/event-dispatcher"><img src="https://img.shields.io/badge/Event%20Dispatcher-PSR--14-7C3AED?logo=github&logoColor=white" alt="Event Dispatcher repository"></a>
  <a href="https://github.com/php-fast-forward/container"><img src="https://img.shields.io/badge/Container-PSR--11-2563EB?logo=github&logoColor=white" alt="Container repository"></a>
  <a href="https://github.com/php-fast-forward/fork"><img src="https://img.shields.io/badge/Fork-parallelism-475569?logo=github&logoColor=white" alt="Fork repository"></a>
  <a href="https://github.com/php-fast-forward/enum"><img src="https://img.shields.io/badge/Enum-domain%20objects-0EA5E9?logo=github&logoColor=white" alt="Enum repository"></a>
</p>

## Why Fast Forward exists

PHP Fast Forward is being built for developers who want to ship quickly and keep an architecture they can evolve. The aim is to reduce framework lock-in, hidden coupling, and repetitive setup while keeping the application's boundaries clear.

Good developer experience should come from useful defaults, community standards, and proven components. Fast Forward starts with PSR interfaces and composition, drawing on projects such as Symfony, Nyholm, PHP-DI, and Laminas instead of asking applications to adopt a new vocabulary for every familiar problem.

The ecosystem includes implemented packages and public repositories that represent future modules. The mission is a complete framework ecosystem where you write fewer classes, configure less by hand, and retain control over the components your application uses. A public repository alone does not mean a module is ready to use; check its README and releases.

<p align="center">
  <img src="../assets/mascot/dash-developer-reading.png" alt="Dash, the Fast Forward fox, reading a book in a purple hoodie" width="300">
</p>

## What makes it different

- **PSR-first by design.** HTTP, containers, events, clocks, factories, and client abstractions use community standards. Each package documents the interfaces it implements.
- **Few classes, little ceremony.** The framework metapackage installs the core stack, and a framework service provider gives it a single bootstrap entrypoint.
- **Composable foundations.** Packages cover configuration, containers, HTTP, PSR-14 events, PSR-20 clocks, iterators, process forking, and more. Choose the pieces your application needs.
- **Proven internals.** Fast Forward builds on established PHP components and focuses its own code on integration, practical defaults, and developer flow.
- **Web and event-driven foundations.** The available HTTP and event-dispatcher packages support request/response and event-driven work. Broader asynchronous capabilities are part of the roadmap.
- **Bridges and adapters.** Integrate existing tools behind clear contracts so applications can depend on stable boundaries rather than vendor-specific details throughout the codebase.
- **Human and agent workflows.** Dev Tools packages reusable skills, project agents, structured command output, and repository automation that people can inspect and review.

## The ecosystem already shipping

- [`fast-forward/framework`](https://github.com/php-fast-forward/framework) bundles the core stack behind a single framework service provider.
- [`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) standardizes quality checks, documentation, repository bootstrap, release work, reusable skills, and project-agent workflows.
- [`fast-forward/http`](https://github.com/php-fast-forward/http) provides an aggregate HTTP stack built around PSR-7, PSR-17, and PSR-18.
- [`fast-forward/event-dispatcher`](https://github.com/php-fast-forward/event-dispatcher) provides PSR-14 dispatching, named events, subscribers, priorities, and attribute-based listeners.
- [`fast-forward/container`](https://github.com/php-fast-forward/container) aggregates PSR-11 containers and service providers.
- [`fast-forward/config`](https://github.com/php-fast-forward/config) loads and combines configuration sources with optional caching and provider support.
- [`fast-forward/enum`](https://github.com/php-fast-forward/enum) provides reusable enum helpers, domain catalogs, and workflow transition helpers.
- [`fast-forward/clock`](https://github.com/php-fast-forward/clock), [`fast-forward/fork`](https://github.com/php-fast-forward/fork), and [`fast-forward/iterators`](https://github.com/php-fast-forward/iterators) provide focused tools for time, forked workers, and iterable data. Forking requires a supported Unix-like CLI runtime with process-control support.

## Public roadmap

Some public repositories describe the intended shape of the ecosystem before their modules are implemented. Planned work includes:

- console utilities and a cleaner CLI layer for applications
- scheduling primitives and orchestration for recurring jobs
- queue and event-bus capabilities for decoupled asynchronous workflows
- more application-facing modules for the complete framework experience

The architectural intent remains the same: consume proven libraries through bridges, adapters, or integration layers with stable Fast Forward contracts. The goal is to help applications remain portable and loosely coupled when an underlying implementation changes. Follow each repository for its implementation and release status.

## The force multiplier: Dev Tools

[`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) handles the repetitive maintenance work shared by many repositories. It works as a Composer plugin and a local binary, with consumer-first configuration that preserves local overrides.

Repositories can synchronize templates, defaults, and operational conventions through the same toolkit. Fast Forward skills and project-agent prompts live there alongside the maintenance commands.

Dev Tools provides:

- documentation and wiki generation, with shared build and publication workflows
- tests, coverage, metrics, code-style checks, PHPDoc checks, and Rector-based refactoring
- dependency analysis, upgrade previews, and assisted upgrades
- changelog authoring and validation, version inference, release-note rendering, and release automation
- CODEOWNERS generation, funding metadata, Git hooks, repository bootstrap, and synchronized workflow stubs
- packaged project agents and reusable skills for people and AI agents

A smaller maintenance checklist gives future-you fewer things to remember.

## The promise in one small snippet

With PHP 8.3 or higher, install the framework metapackage in your project:

```sh
composer require fast-forward/framework
```

Then load Composer's autoloader and register the framework provider:

```php
<?php

declare(strict_types=1);

use FastForward\Framework\ServiceProvider\FrameworkServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/vendor/autoload.php';

$container = container(FrameworkServiceProvider::class);
```

This bootstraps the core services. The [framework README](https://github.com/php-fast-forward/framework) shows how to retrieve them. That is the direction: fewer classes, less manual plumbing, useful defaults, and clearer boundaries.

## Build with us

Dash, the Fast Forward fox, welcomes contributors and accompanies the documentation. The shared [brand and mascot kit](../assets/README.md) defines the character, visual system, reusable assets, and reference artwork. Use it when creating a package banner, documentation page, or community contribution.

The [public visual library](https://php-fast-forward.github.io/.github/) is the GitHub Pages destination for logo variants, mascot poses, and documentation patterns. Its source remains in this repository.

Explore the [organization](https://github.com/php-fast-forward), read the package guides, open an issue with a reproducible example, or send a focused pull request. Help shape a PHP ecosystem that moves development forward while keeping architecture understandable.

<p align="center">
  <img src="../assets/mascot/dash-developer-welcome.png" alt="Dash, the Fast Forward fox, welcoming contributors in a purple hoodie" width="260">
</p>
