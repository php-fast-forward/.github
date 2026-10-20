<?php

declare(strict_types=1);

use PhpFastForward\Brand\BrandIcons;
use PHPUnit\Framework\TestCase;

final class BrandIconsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function test_icon_exports_retain_the_selected_vector_paths_and_clear_space(): void
    {
        $source = json_decode(file_get_contents(self::ROOT . '/assets/brand/logo-source.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (BrandIcons::vectors(self::ROOT) as $path => $svg) {
            self::assertSame(file_get_contents(self::ROOT . '/' . $path), $svg);
            $xml = new DOMDocument();
            self::assertTrue($xml->loadXML($svg, LIBXML_NONET));
            $xpath = new DOMXPath($xml);
            $xpath->registerNamespace('s', 'http://www.w3.org/2000/svg');
            self::assertSame(0, $xpath->query('//s:image|//s:text|//s:script')->length);
            self::assertSame('translate(' . (str_contains($path, 'favicon') ? '16 16' : '80 80') . ')', $xpath->query('//s:g')->item(0)->getAttribute('transform'));
            foreach ($source['symbol']['paths'] as $part) {
                self::assertStringContainsString($part['d'], $svg);
            }
        }
    }

    public function test_raster_receipts_are_bound_to_current_vectors_and_exact_sizes(): void
    {
        $receipt = json_decode(file_get_contents(self::ROOT . '/docs/brand/icon-validation.json'), true, flags: JSON_THROW_ON_ERROR);
        $sizes = [];
        foreach ($receipt['exports'] as $export) {
            if ($export['format'] !== 'png') {
                continue;
            }
            $path = self::ROOT . '/' . $export['path'];
            self::assertSame($export['sha256'], hash_file('sha256', $path));
            self::assertSame($export['source_sha256'], hash_file('sha256', self::ROOT . '/' . $export['source_svg']));
            [$width, $height, $format] = getimagesize($path);
            self::assertSame($export['width'], $width);
            self::assertSame($width, $height);
            self::assertSame(IMAGETYPE_PNG, $format);
            $sizes[] = $width;
        }
        sort($sizes);
        self::assertSame([16, 32, 48, 64, 128, 256, 512], $sizes);
    }
}

