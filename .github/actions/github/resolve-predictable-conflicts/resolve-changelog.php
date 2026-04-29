<?php

declare(strict_types=1);

use FastForward\DevTools\Changelog\Conflict\UnreleasedChangelogConflictResolver;
use FastForward\DevTools\Changelog\Parser\ChangelogParser;
use FastForward\DevTools\Changelog\Renderer\MarkdownRenderer;

$autoload = getenv('DEV_TOOLS_AUTO_RESOLVE_AUTOLOAD') ?: getcwd() . '/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, sprintf("Composer autoload file not found: %s\n", $autoload));

    exit(2);
}

require $autoload;

$options = getopt('', [
    'target:',
    'source:',
    'output:',
    'repository-url::',
]);

$target = is_string($options['target'] ?? null) ? $options['target'] : null;
$source = is_string($options['source'] ?? null) ? $options['source'] : null;
$output = is_string($options['output'] ?? null) ? $options['output'] : null;
$repositoryUrl = is_string($options['repository-url'] ?? null) ? $options['repository-url'] : null;

if (null === $target || null === $source || null === $output) {
    fwrite(STDERR, "Usage: resolve-changelog.php --target=<file> --source=<file> --output=<file> [--repository-url=<url>]\n");

    exit(2);
}

$targetContents = file_get_contents($target);
$sourceContents = file_get_contents($source);

if (! is_string($targetContents) || ! is_string($sourceContents)) {
    fwrite(STDERR, "Unable to read changelog conflict stages.\n");

    exit(2);
}

$resolver = new UnreleasedChangelogConflictResolver(new ChangelogParser(), new MarkdownRenderer());
$resolved = $resolver->resolve($targetContents, [$sourceContents], $repositoryUrl);

file_put_contents($output, $resolved);
