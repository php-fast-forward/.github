# Resolve Dash references

Run this read-only example after selecting a reviewed `php-fast-forward/brand-kit ^0.3`
installation/checkout. PHP 8.4+ with its standard JSON/hash/file functions is
sufficient; no Composer autoloader, network request or image-generator SDK is used.

From a consumer repository, copy [the example brief](repository-header.json) to
`dash-brief.json`, adapt it to the repository, then run:

```sh
php "$DASH_SKILL_ROOT/examples/resolve-references.php" "$DASH_BRAND_ROOT" ./dash-brief.json
```

Exit 0 returns `ready-not-generated` with the verified identity and optional
pose-support records. Prefix their relative paths with your selected kit root
when opening/supplying the actual reference files. Keep receipt paths relative
when committing public production records.

The program rejects the wrong kit, invalid/missing brief input, a mismatched
family master, exploratory pose sources, missing/duplicate IDs, symlink/traversal
paths and changed file hashes. Exit 1 reports input/reference failure; exit 2 is
incorrect invocation. Correct the input/kit rather than bypassing the check.
The program never writes a brief/image, installs software or changes the kit.
It verifies bytes and selection; human/agent inspection still establishes likeness.

The installed-version/release check belongs to the surrounding procedure:
the example reads file identity, not Composer's release registry. Successful
resolution does not prove host skill activation, image generation or publication.

