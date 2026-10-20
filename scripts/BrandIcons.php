<?php

declare(strict_types=1);

namespace PhpFastForward\Brand;

use RuntimeException;

/** Dedicated small exports from the selected fox geometry, never full-body art. */
final class BrandIcons
{
    /** @return array<string, string> */
    public static function vectors(string $root): array
    {
        $source = json_decode(file_get_contents($root . '/assets/brand/logo-source.json'), true, flags: JSON_THROW_ON_ERROR);
        $mark = BrandLogos::render($source, 'dark', true);
        $body = preg_replace('/^.*?<\/title>/s', '', $mark);
        $body = preg_replace('/<\/svg>\s*$/', '', $body);
        $outputs = [];
        foreach (['favicon' => [352, 16, 64], 'dash-avatar' => [480, 80, 112]] as $name => [$size, $padding, $radius]) {
            $outputs['assets/icons/' . $name . '.svg'] = sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-labelledby="title">' . "\n"
                . '<title id="title">Dash fox %2$s</title>' . "\n"
                . '<rect width="%1$d" height="%1$d" rx="%3$d" fill="#050B20"/>' . "\n"
                . '<g transform="translate(%4$d %4$d)">%5$s</g>' . "\n</svg>\n",
                $size, $name === 'favicon' ? 'favicon' : 'avatar', $radius, $padding, $body,
            );
        }

        return $outputs;
    }

    public static function build(string $root, bool $check, bool $raster): void
    {
        foreach (self::vectors($root) as $relative => $svg) {
            $path = $root . '/' . $relative;
            if ($check) {
                if (!is_file($path) || file_get_contents($path) !== $svg) {
                    throw new RuntimeException('Stale icon vector: ' . $relative);
                }
                continue;
            }
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true)) {
                throw new RuntimeException('Cannot create icon directory');
            }
            if (file_put_contents($path, $svg) === false) {
                throw new RuntimeException('Cannot write icon vector');
            }
            if (!$raster) {
                continue;
            }
            $sizes = str_contains($relative, 'favicon') ? [16, 32, 48] : [64, 128, 256, 512];
            foreach ($sizes as $size) {
                $output = substr($path, 0, -4) . '-' . $size . '.png';
                $process = proc_open(
                    ['rsvg-convert', '--width', (string) $size, '--height', (string) $size, '--output', $output, $path],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
                );
                if (!is_resource($process)) {
                    throw new RuntimeException('Install librsvg (rsvg-convert) to export PNG icons');
                }
                fclose($pipes[0]);
                stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                if (proc_close($process) !== 0) {
                    throw new RuntimeException('PNG export failed: ' . $error);
                }
            }
        }
    }
}

