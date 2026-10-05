<?php

declare(strict_types=1);

require __DIR__ . '/BrandLogos.php';

use PhpFastForward\Brand\BrandLogos;

if (array_diff(array_slice($argv, 1), ['--check'])) {
    fwrite(STDERR, "Usage: php scripts/build-brand-logos.php [--check]\n");
    exit(2);
}
try {
    $check = in_array('--check', $argv, true);
    $count = BrandLogos::build(dirname(__DIR__), $check);
    echo ($check ? 'Verified' : 'Built') . " {$count} SVG logo exports.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
