<?php

declare(strict_types=1);

use PhpFastForward\Brand\BrandLogos;
use PHPUnit\Framework\TestCase;

final class BrandLogosTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const VARIANTS = ['color', 'dark', 'ink', 'white'];

    private function source(): array
    {
        return json_decode(file_get_contents(self::ROOT . '/assets/brand/logo-source.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function path(string $stem, string $variant): string
    {
        return 'assets/brand/fast-forward-' . $stem . ($variant === 'color' ? '' : '-' . $variant) . '.svg';
    }

    private function tree(string $stem, string $variant): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->load(self::ROOT . '/' . $this->path($stem, $variant), LIBXML_NONET));

        return $document;
    }

    private function paths(DOMNode $node): array
    {
        $xpath = new DOMXPath($node instanceof DOMDocument ? $node : $node->ownerDocument);

        return iterator_to_array($xpath->query('.//*[local-name()="path"]', $node));
    }

    private function geometry(array $paths): array
    {
        $geometry = array_count_values(array_map(fn ($path) => $path instanceof DOMElement ? $path->getAttribute('d') : $path['d'], $paths));
        ksort($geometry);

        return $geometry;
    }

    public function testAllVariantsShareApprovedGeometryAndLettering(): void
    {
        $symbol = $this->geometry($this->source()['symbol']['paths']);
        $reference = null;
        $box = null;
        foreach (self::VARIANTS as $variant) {
            $mark = $this->tree('mark', $variant);
            $logo = $this->tree('logo', $variant);
            self::assertSame($symbol, $this->geometry($this->paths($mark)));
            self::assertSame('0 0 320 320', $mark->documentElement->getAttribute('viewBox'));
            $paths = $this->paths($logo);
            self::assertSame($symbol, $this->geometry(array_filter($paths, fn ($path) => !$path->hasAttribute('transform'))));
            $letters = array_values(array_map(fn ($path) => [$path->getAttribute('d'), $path->getAttribute('transform')], array_filter($paths, fn ($path) => $path->hasAttribute('transform'))));
            self::assertCount(strlen('PHPFastForward'), $letters);
            $reference ??= $letters;
            $box ??= $logo->documentElement->getAttribute('viewBox');
            self::assertSame($reference, $letters);
            self::assertSame($box, $logo->documentElement->getAttribute('viewBox'));
        }
    }

    public function testFastIsOrangeAndForwardUsesSurfaceColor(): void
    {
        foreach (['color', 'dark'] as $variant) {
            $letters = array_values(array_filter($this->paths($this->tree('logo', $variant)), fn ($path) => $path->hasAttribute('transform')));
            $colors = $this->source()['variants'][$variant];
            self::assertSame(array_fill(0, 4, $colors['orange']), array_map(fn ($path) => $path->getAttribute('fill'), array_slice($letters, 3, 4)));
            self::assertSame(array_fill(0, strlen('PHPForward'), $colors['lettering']), array_map(fn ($path) => $path->getAttribute('fill'), array_merge(array_slice($letters, 0, 3), array_slice($letters, 7))));
        }
    }

    public function testMonochromeUsesOneVisibleColorWithCreamKnockout(): void
    {
        foreach (['ink', 'white'] as $variant) {
            foreach (['logo', 'mark'] as $stem) {
                $tree = $this->tree($stem, $variant);
                $xpath = new DOMXPath($tree);
                $fills = array_map(fn ($element) => $element->getAttribute('fill'), iterator_to_array($xpath->query('//*[@fill and not(ancestor::*[local-name()="defs"])]')));
                self::assertSame([$this->source()['variants'][$variant]['lettering']], array_values(array_unique($fills)));
                $masks = $xpath->query('//*[local-name()="mask"]');
                self::assertSame(1, $masks->length);
                self::assertSame($this->geometry(array_filter($this->source()['symbol']['paths'], fn ($part) => $part['role'] === 'cream')), $this->geometry($this->paths($masks->item(0))));
                self::assertSame(1, $xpath->query('//*[@mask="url(#fox-knockout)"]')->length);
            }
        }
    }

    public function testExportsHavePathsWithoutRasterTextOrExternalFont(): void
    {
        foreach (self::VARIANTS as $variant) {
            foreach (['logo', 'mark'] as $stem) {
                $tree = $this->tree($stem, $variant);
                self::assertNotEmpty($this->paths($tree));
                foreach ($tree->getElementsByTagName('*') as $element) {
                    self::assertNotContains(strtolower($element->localName), ['image', 'text', 'script', 'foreignobject', 'style']);
                    foreach ($element->attributes as $attribute) {
                        if ($attribute->name === 'xmlns') {
                            continue;
                        }
                        self::assertNotContains(strtolower($attribute->localName), ['font-family', 'font-face', 'href']);
                        foreach (['data:image', 'https:', 'http:'] as $external) {
                            self::assertStringNotContainsString($external, strtolower($attribute->value));
                        }
                    }
                }
            }
        }
    }

    public function testFontReceiptAndDistributedBytesAgree(): void
    {
        $lettering = $this->source()['lettering'];
        $receipt = json_decode(file_get_contents(self::ROOT . '/assets/brand/source/font-source.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['font_sha256' => $receipt['file']['sha256'], 'source_commit' => $receipt['upstream']['commit'], 'font_family' => $receipt['family'], 'font_style' => $receipt['style']] as $key => $value) {
            self::assertSame($value, $lettering[$key]);
        }
        self::assertSame($receipt['lettering_instance'], $lettering['authoring']['instance']);
        $catalog = array_column($lettering['source_files'], 'sha256', 'path');
        foreach ([$receipt['file'], $receipt['license']] as $item) {
            $path = self::ROOT . '/' . $item['path'];
            self::assertSame($item['bytes'], filesize($path));
            self::assertSame($item['sha256'], hash_file('sha256', $path));
            self::assertSame($item['sha256'], $catalog[$item['path']]);
        }
        foreach ($catalog as $relative => $digest) {
            self::assertSame($digest, hash_file('sha256', self::ROOT . '/' . $relative));
        }
    }

    public function testPathQuotesCannotCreateSvgAttributes(): void
    {
        $path = 'M0 0" onload="fixture';
        $svg = BrandLogos::render(['symbol' => ['paths' => [['role' => 'orange', 'd' => $path]]], 'variants' => ['color' => ['orange' => '#f00']]], 'color', true);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($svg, LIBXML_NONET));
        $element = $document->getElementsByTagName('path')->item(0);
        self::assertSame($path, $element->getAttribute('d'));
        self::assertFalse($element->hasAttribute('onload'));
    }

    public function testPaintValuesCannotCreateSvgAttributes(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('opaque hexadecimal colors');
        BrandLogos::render(['symbol' => ['paths' => [['role' => 'orange', 'd' => 'M0 0']]], 'variants' => ['color' => ['orange' => '#fff" onload="fixture']]], 'color', true);
    }

    private function isolated(Closure $test): void
    {
        $root = sys_get_temp_dir() . '/brand-logo-' . bin2hex(random_bytes(8));
        $paths = ['scripts/build-brand-logos.php', 'scripts/BrandLogos.php', 'assets/brand/logo-source.json', ...array_column($this->source()['lettering']['source_files'], 'path')];
        foreach ($paths as $relative) {
            if (!is_dir(dirname($root . '/' . $relative))) {
                mkdir(dirname($root . '/' . $relative), 0777, true);
            }
            copy(self::ROOT . '/' . $relative, $root . '/' . $relative);
        }
        try {
            $test($root);
        } finally {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }

    private function runBuilder(string $root, array $arguments = [], int $expected = 0): string
    {
        $process = proc_open([PHP_BINARY, $root . '/scripts/build-brand-logos.php', ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        fclose($pipes[0]);
        $text = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame($expected, proc_close($process), $text);

        return $text;
    }

    private function bytes(string $root): array
    {
        $bytes = [];
        foreach (self::VARIANTS as $variant) {
            foreach (['logo', 'mark'] as $stem) {
                $relative = $this->path($stem, $variant);
                $bytes[$relative] = file_get_contents($root . '/' . $relative);
            }
        }

        return $bytes;
    }

    public function testBuildIsDeterministicAndMatchesDeliveredExports(): void
    {
        $this->isolated(function (string $root): void {
            $this->runBuilder($root);
            $first = $this->bytes($root);
            $this->runBuilder($root);
            self::assertSame($first, $this->bytes($root));
            self::assertSame($this->bytes(self::ROOT), $first);
            self::assertStringContainsString('Verified 8 SVG logo exports.', $this->runBuilder($root, ['--check']));
            self::assertSame($first, $this->bytes($root));
        });
    }

    public function testCheckRejectsStaleAndMissingExportsWithoutRewriting(): void
    {
        $this->isolated(function (string $root): void {
            $this->runBuilder($root);
            $stale = $root . '/' . $this->path('logo', 'color');
            file_put_contents($stale, "<!-- local mutation -->\n", FILE_APPEND);
            $before = $this->bytes($root);
            self::assertStringContainsString('Stale logo export:', $this->runBuilder($root, ['--check'], 1));
            self::assertSame($before, $this->bytes($root));
            unlink($stale);
            self::assertStringContainsString('Stale logo export:', $this->runBuilder($root, ['--check'], 1));
            self::assertFileDoesNotExist($stale);
        });
    }

    public function testFontLicenseAndReceiptDriftStopBuildAndCheck(): void
    {
        $this->isolated(function (string $root): void {
            $this->runBuilder($root);
            $before = $this->bytes($root);
            foreach ($this->source()['lettering']['source_files'] as $item) {
                $path = $root . '/' . $item['path'];
                $original = file_get_contents($path);
                file_put_contents($path, "\nfixture drift\n", FILE_APPEND);
                foreach ([['--check'], []] as $arguments) {
                    self::assertStringContainsString('Font source changed: ' . $item['path'], $this->runBuilder($root, $arguments, 1));
                    self::assertSame($before, $this->bytes($root));
                }
                file_put_contents($path, $original);
            }
        });
    }
}
