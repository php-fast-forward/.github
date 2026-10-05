<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpFastForward\Brand\BrandPages;

$options = getopt('', ['root:', 'output-dir:', 'repository-url:', 'source-ref:']);
if (!isset($options['output-dir'])) {
    fwrite(STDERR, "Usage: php scripts/build-brand-pages.php --output-dir DIRECTORY [--root DIRECTORY] [--source-ref REF]\n");
    exit(2);
}
try {
    $count = BrandPages::build($options['root'] ?? dirname(__DIR__), $options['output-dir'], $options['repository-url'] ?? 'https://github.com/php-fast-forward/.github', $options['source-ref'] ?? 'main');
    echo "Staged {$count} public files in {$options['output-dir']}; links and anchors verified.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Pages build failed: {$error->getMessage()}\n");
    exit(1);
}
