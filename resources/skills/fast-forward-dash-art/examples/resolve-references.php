<?php

declare(strict_types=1);

/** Read-only consumer example: resolve selected Dash masters from a reviewed kit. */
if (count($argv) !== 3) {
    fwrite(STDERR, "Usage: php resolve-references.php <brand-kit-root> <brief.json>\n");
    exit(2);
}

try {
    $root = realpath($argv[1]);
    if ($root === false || !is_file($root . '/DESIGN.md')) {
        throw new RuntimeException('Provide a reviewed brand-kit root containing DESIGN.md');
    }
    $readJson = static function (string $path): array {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('Cannot read input: ' . basename($path));
        }
        $value = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('JSON input must contain an object');
        }

        return $value;
    };
    if (($readJson($root . '/composer.json')['name'] ?? null) !== 'php-fast-forward/brand-kit') {
        throw new RuntimeException('The selected directory is not the PHP Fast Forward brand kit');
    }
    $brief = $readJson($argv[2]);
    foreach (['audience', 'purpose', 'package', 'pose', 'surface', 'text_surface'] as $field) {
        if (!is_string($brief[$field] ?? null) || trim($brief[$field]) === '') {
            throw new RuntimeException('Missing brief field: ' . $field);
        }
    }
    $family = $brief['family'] ?? null;
    $master = match ($family) {
        'developer' => 'dash-developer-welcome',
        'editorial' => 'dash-editorial-hoodie',
        default => throw new RuntimeException('Choose developer or editorial family'),
    };
    if (($brief['required_master_id'] ?? null) !== $master) {
        throw new RuntimeException('Required identity master does not match the selected family');
    }
    $output = $brief['output'] ?? [];
    if (($output['format'] ?? null) !== 'png'
        || !is_int($output['width'] ?? null) || $output['width'] < 1
        || !is_int($output['height'] ?? null) || $output['height'] < 1
        || !is_bool($output['transparent_background'] ?? null)) {
        throw new RuntimeException('Choose PNG, positive integer dimensions and an explicit transparency boolean');
    }
    $inventory = $readJson($root . '/assets/manifest.json');
    $assets = [];
    foreach ($inventory['assets'] ?? [] as $asset) {
        if (!is_string($asset['id'] ?? null) || isset($assets[$asset['id']])) {
            throw new RuntimeException('Invalid or duplicate asset identifier');
        }
        $assets[$asset['id']] = $asset;
    }
    $ids = [$master];
    if (isset($brief['preferred_pose_id'])) {
        if (!is_string($brief['preferred_pose_id'])) {
            throw new RuntimeException('Preferred pose ID must be a string');
        }
        $ids[] = $brief['preferred_pose_id'];
    }
    $references = [];
    foreach (array_unique($ids) as $id) {
        $asset = $assets[$id] ?? throw new RuntimeException('Missing required reference: ' . $id);
        if (($asset['family'] ?? null) !== $family
            || !in_array($asset['status'] ?? null, ['canonical', 'package-variant'], true)) {
            throw new RuntimeException('Reference is not a selected pose in the requested family: ' . $id);
        }
        $relative = $asset['path'] ?? '';
        if (!is_string($relative) || $relative === '' || str_contains($relative, '\\')
            || str_starts_with($relative, '/') || str_contains($relative, "\0")
            || preg_match('~(^|/)\\.\\.?(/|$)~', $relative)) {
            throw new RuntimeException('Unsafe reference path');
        }
        $path = $root;
        foreach (explode('/', $relative) as $segment) {
            $path .= '/' . $segment;
            if ($segment === '' || is_link($path)) {
                throw new RuntimeException('Reference path must not contain empty segments or symlinks');
            }
        }
        if (!is_file($path) || !preg_match('/^[a-f0-9]{64}$/D', $asset['sha256'] ?? '')
            || hash_file('sha256', $path) !== $asset['sha256']) {
            throw new RuntimeException('Missing or changed reference bytes: ' . $id);
        }
        $references[] = ['id' => $id, 'role' => $id === $master ? 'identity' : 'pose-support', 'path' => $relative, 'sha256' => $asset['sha256']];
    }
    echo json_encode(['status' => 'ready-not-generated', 'brief' => $brief, 'references' => $references], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

