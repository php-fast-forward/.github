<?php

declare(strict_types=1);

require __DIR__ . '/BrandLogos.php';
require __DIR__ . '/BrandIcons.php';

use PhpFastForward\Brand\BrandIcons;

$options = array_slice($argv, 1);
if (array_diff($options, ['--check', '--raster']) || (in_array('--check', $options, true) && in_array('--raster', $options, true))) {
    fwrite(STDERR, "Usage: php scripts/build-brand-icons.php [--check | --raster]\n");
    exit(2);
}
try {
    BrandIcons::build(dirname(__DIR__), in_array('--check', $options, true), in_array('--raster', $options, true));
    echo "Icon vectors verified or exported; PNG authoring requires --raster and rsvg-convert.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

