#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/BrandValidator.php';

use PhpFastForward\Brand\BrandValidator;

$root = dirname(__DIR__);
for ($index = 1; $index < $argc; ++$index) {
    if ($argv[$index] === '--root' && isset($argv[$index + 1])) {
        $root = $argv[++$index];
    } elseif (str_starts_with($argv[$index], '--root=')) {
        $root = substr($argv[$index], 7);
    } elseif (in_array($argv[$index], ['--help', '-h'], true)) {
        echo "Usage: php scripts/validate-brand.php [--root repository]\n";
        exit(0);
    } else {
        fwrite(STDERR, 'Unknown or incomplete argument: ' . $argv[$index] . "\n");
        exit(2);
    }
}
$result = BrandValidator::validate($root);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($result['valid'] ? 0 : 1);
