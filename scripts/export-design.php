#!/usr/bin/env php
<?php

declare(strict_types=1);

use PhpFastForward\Brand\DesignExports;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
$check = in_array('--check', $arguments, true);
$tokensOnly = in_array('--tokens-only', $arguments, true);
if (($check && $tokensOnly) || count($arguments) > 1 || array_diff($arguments, ['--check', '--tokens-only'])) {
    fwrite(STDERR, "Usage: php scripts/export-design.php [--check | --tokens-only]\n");
    exit(2);
}

try {
    $root = dirname(__DIR__);
    $outputs = DesignExports::generate(file_get_contents($root . '/DESIGN.md'));
    if (!$tokensOnly) {
        DesignExports::verifyBrowserAdapter($outputs['theme.css']['value'], file_get_contents($root . '/assets/styles/documentation.css'));
    }
    $metadata = DesignExports::json(DesignExports::metadata($root, $outputs));
    $directory = $root . '/assets/tokens';
    if ($check) {
        foreach ($outputs as $name => $output) {
            if (!is_file($directory . '/' . $name) || file_get_contents($directory . '/' . $name) !== $output['value']) {
                throw new RuntimeException("{$name} is stale; run php scripts/export-design.php");
            }
        }
        if (!is_file($directory . '/exports.json') || file_get_contents($directory . '/exports.json') !== $metadata) {
            throw new RuntimeException('Export source or output hashes are stale');
        }
    } else {
        if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
            throw new RuntimeException('Could not create token output directory');
        }
        foreach ($outputs as $name => $output) {
            if (file_put_contents($directory . '/' . $name, $output['value']) === false) {
                throw new RuntimeException("Could not write {$name}");
            }
        }
        if (file_put_contents($directory . '/exports.json', $metadata) === false) {
            throw new RuntimeException('Could not write export metadata');
        }
    }
    echo json_encode(['valid' => true, 'mode' => $check ? 'check' : ($tokensOnly ? 'tokens-only' : 'write'), 'browser_adapter_checked' => !$tokensOnly, 'generator' => 'php scripts/export-design.php', 'outputs' => count($outputs)], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
