# Install the Dash artwork skill

The public skill name is **fast-forward-dash-art**. Its single source is
`resources/skills/fast-forward-dash-art/` in
[php-fast-forward/.github](https://github.com/php-fast-forward/.github).
It supports the `php-fast-forward/brand-kit ^0.3` contract. Its instructions
and examples have their own license; image/font rights remain separate.

Installing a skill copies instructions into a discovery directory. It does
not install the full brand library, an image-generation service or a host
session. The artwork procedure still needs a reviewed kit supplied through
`DASH_BRAND_ROOT`. Follow existing project trust/settings rather than replacing
an application's `skills.json`, aliases or plugin approvals wholesale.

## Preferred: explicit GitHub donor through LLM Skills

Use this when you want the skill without adding the whole brand kit as a PHP
dependency. Requirements: Composer, PHP 8.2+ and the extensions required by
`llm/skills ^1.15`, authorized GitHub read access if necessary, and permission
to enable that Composer plugin in this consumer project.

Before first installation/sync, merge this into the consumer's `skills.json`:

```json
{
  "target": ".agents/skills",
  "aliases": [],
  "discovery": false,
  "auto-sync": true,
  "vendor-sources": false,
  "dependencies": {
    "composer": false
  },
  "sources": [
    {
      "from": "github",
      "package": "php-fast-forward/.github",
      "ref": "v0.3.0",
      "skills": ["fast-forward-dash-art"]
    }
  ]
}
```

Select a reviewed full release tag (v0.3.0 or a compatible later release) or
its immutable commit. Preserve unrelated sources. Disable Composer donor
discovery only if that is your project's intended policy; this example selects
the GitHub source explicitly. It requests no extra host aliases.

With existing authority to install/enable this project plugin:

```sh
composer config allow-plugins.llm/skills true
composer require --dev 'llm/skills:^1.15'
composer skills:show
```

The expected project copy is
`.agents/skills/fast-forward-dash-art/SKILL.md`, with its LICENSE, references
and examples. Inspect those bytes and the tool report: a successful Composer
exit alone does not prove a copy was installed. Existing approved install/update
events synchronize it; no post-install script or runtime dependency is needed.

For explicit synchronization, set `auto-sync:false` **before** plugin
installation, then inspect and apply:

```sh
composer skills:update --from=github --dry-run
composer skills:update --from=github
```

A pinned ref stays pinned: review/edit it before requesting a new revision.
Commit the intended project Composer files and `skills.json` according to
that repository's policy. Do not overwrite unrelated source configuration.

## Optional: installed Composer donor

Use this route only when the application deliberately wants the complete
brand-kit package and meets its PHP 8.4+, DOM, JSON and zlib requirements.
It is a project-type package; the donor metadata does not turn it into an
installer/plugin or assert Packagist availability.

Supply its reviewed VCS repository in existing Composer configuration:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/php-fast-forward/.github.git"
    }
  ],
  "require-dev": {
    "php-fast-forward/brand-kit": "^0.3",
    "llm/skills": "^1.15"
  },
  "config": {
    "allow-plugins": {
      "llm/skills": true
    }
  }
}
```

Merge existing keys. Before installing, prepare the narrowly trusted donor:

```json
{
  "target": ".agents/skills",
  "aliases": [],
  "discovery": false,
  "auto-sync": true,
  "vendor-sources": false,
  "dependencies": {
    "composer": {
      "trusted": ["php-fast-forward/brand-kit"],
      "trusted-replace": true
    }
  }
}
```

Then run `composer install` or the repository's authorized update command.
The donor declares `extra.skills.source: resources/skills`; LLM Skills copies
that complete package rather than linking to replaceable vendor content.
A suggestion alone neither installs nor approves the plugin.

For explicit donor sync, use `auto-sync:false` and inspect/apply
`composer skills:update php-fast-forward/brand-kit --dry-run`, then the same
command without `--dry-run`. A named package selects/trusts it for that run;
it does not test the unfiltered automatic allowlist.

## Update, suppression and removal

Copies are installer-managed: same-path incoming files can replace local edits.
Additional local files and files removed upstream may remain. Removing the
donor or plugin does not establish removal of an already copied skill.
Removing a GitHub source from configuration likewise needs inspection of the
existing copied directory. Keep a separately named consumer-owned skill for
intentional local customizations.

With Composer 2.10.3 / llm/skills 1.15.0, `--no-scripts` alone did not disable
automatic synchronization. Set `auto-sync:false` before the event or use
`--no-plugins` to avoid loading the plugin. Verify behavior after tool changes.

Never add a blanket clean hook. `composer skills:update --clean --dry-run`
is a preview; an unscoped clean can remove unrelated/manual skills. A scoped
clean cannot identify the owner of a donor already removed. Inventory and
archive only the selected stale directory outside discovery before an
authorized removal/replacement.

To migrate an older `dash-art` installation, inspect its source/ownership
first, archive that exact old directory outside discovery, then install the
new name. The source move does not remove old copies. No duplicate donor source
or legacy host alias is shipped.

## Guarded manual project or global copy

Use a complete source package from a reviewed kit, including LICENSE,
references and examples. Project scope is the default:

```sh
skill_source="$DASH_BRAND_ROOT/resources/skills/fast-forward-dash-art"
skill_target=".agents/skills/fast-forward-dash-art"
test -f "$skill_source/SKILL.md" &&
test ! -e "$skill_target" &&
test ! -L "$skill_target" &&
mkdir -p "$(dirname "$skill_target")" &&
cp -R "$skill_source" "$skill_target"
```

For an explicitly requested global Codex copy, choose
`$HOME/.agents/skills/fast-forward-dash-art` as the target instead. A
Claude-only project may select `.claude/skills`. Honor the actual host's
configured discovery location. Installing the Composer plugin globally does
not install these files into a host's global discovery root.

For a reviewed update/removal, archive only the selected copy:

```sh
skill_backup="$HOME/.agent-skill-backups/fast-forward-dash-art-$(date +%Y%m%d%H%M%S)-$$"
test -d "$skill_target" &&
test ! -L "$skill_target" &&
test ! -e "$skill_backup" &&
test ! -L "$skill_backup" &&
mkdir -p "$(dirname "$skill_backup")" &&
mv "$skill_target" "$skill_backup"
```

Then apply the guarded copy for an update, or leave it outside discovery for
removal. To roll back, archive any replacement and restore the complete saved
directory to the original target. Configure the installer source/trust
accordingly, or automatic synchronization may recreate the managed copy.

## Verify the consumer task

Inspect the installed `SKILL.md`, LICENSE, references and PHP example.
Refresh the intended agent session, confirm that it discovers
`fast-forward-dash-art`, then request a bounded contextual artwork brief.
File copies, installer inventory, native host activation and successful artwork
production are separate observations.

Read the [artwork procedure](../../resources/skills/fast-forward-dash-art/SKILL.md)
and [kit production guide](mascot-production.md) for actual image/reference use.

Primary tool contracts: [LLM Skills 1.15.0](https://github.com/roxblnfk/skills/tree/1.15.0),
[Composer plugin approval](https://getcomposer.org/doc/06-config.md#allow-plugins)
and [Agent Skills format](https://agentskills.io/specification).

