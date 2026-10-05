<?php

declare(strict_types=1);

use PhpFastForward\Brand\BrandPages;
use PHPUnit\Framework\TestCase;

final class BrandPagesTest extends TestCase
{
    private string $temporary;
    private string $root;
    private string $output;
    private array $rows;
    private const REPOSITORY = 'https://github.com/php-fast-forward/.github';

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/brand-pages-' . bin2hex(random_bytes(8));
        $this->root = $this->temporary . '/repository';
        $this->output = $this->temporary . '/site';
        $this->rows = [];
        $this->write('assets/README.md', "# Assets\n\n[Manifest](manifest.json)\n");
        $this->write('DESIGN.md', "# Design\n");
        $this->write('docs/brand/index.html', '<!doctype html><html><body id="main"><img src="../../assets/current.png" alt="Current"><a href="../../DESIGN.md">Design</a><a href="../../assets/manifest.json">Catalog</a></body></html>');
        $this->asset('assets/current.png', 'canonical');
        $this->manifest();
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->temporary);
    }

    private function write(string $relative, string $content): string
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    private function asset(string $relative, string $status): void
    {
        $path = $this->write($relative, 'synthetic public fixture');
        $this->rows[] = ['id' => $relative, 'path' => $relative, 'status' => $status, 'sha256' => hash_file('sha256', $path)];
    }

    private function manifest(array $extra = []): void
    {
        $this->write('assets/manifest.json', json_encode(['schema_version' => 1, 'description' => 'Synthetic inventory.', 'assets' => $this->rows] + $extra, JSON_THROW_ON_ERROR));
    }

    private function build(string $ref = 'main'): int
    {
        return BrandPages::build($this->root, $this->output, self::REPOSITORY, $ref);
    }

    private function rejects(string $message, ?Closure $operation = null, bool $beforeOutput = true): void
    {
        try {
            ($operation ?? fn () => $this->build())();
            self::fail('Expected rejected build: ' . $message);
        } catch (RuntimeException $error) {
            self::assertStringContainsString($message, $error->getMessage());
        }
        if ($beforeOutput) {
            self::assertDirectoryDoesNotExist($this->output);
        }
    }

    public function testExplicitInventoryExcludesPrivateUntrackedAndHistoricalFiles(): void
    {
        foreach (['assets/legacy.png' => 'legacy', 'references/source.png' => 'reference', 'assets/study.png' => 'exploratory', 'assets/styles/navigation.css' => 'reference', 'profile/assets/brand-hero-banner.png' => 'canonical', 'profile/assets/docs-installation.png' => 'canonical'] as $path => $status) {
            $this->asset($path, $status);
        }
        foreach (['profile/assets/github-social-preview.jpg', 'assets/untracked-photo.jpg', 'backup/source.png', 'private/photo.jpg', 'assets/brand/source/OFL.txt', 'assets/brand/source/font.ttf', 'scripts/build-brand-logos.php', 'scripts/BrandLogos.php'] as $path) {
            $this->write($path, 'fixture');
        }
        $this->manifest();
        $count = $this->build();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->output, FilesystemIterator::SKIP_DOTS));
        self::assertSame($count, count(iterator_to_array($files)));
        foreach (['references/source.png', 'assets/legacy.png', 'assets/untracked-photo.jpg', 'profile/assets/github-social-preview.jpg', 'backup/source.png', 'private/photo.jpg'] as $path) {
            self::assertFileDoesNotExist($this->output . '/' . $path);
        }
        foreach (['assets/current.png', 'assets/study.png', 'assets/styles/navigation.css', 'profile/assets/brand-hero-banner.png', 'profile/assets/docs-installation.png', 'assets/brand/source/OFL.txt', 'assets/brand/source/font.ttf', 'scripts/build-brand-logos.php', 'scripts/BrandLogos.php'] as $path) {
            self::assertFileExists($this->output . '/' . $path);
        }
        self::assertStringContainsString('url=docs/brand/index.html', file_get_contents($this->output . '/index.html'));
    }

    public function testReferenceImagesBecomeLinksAndPublicPathsStayRelative(): void
    {
        $this->write('references/source.png', 'reference');
        $this->write('docs/brand/index.html', '<html><body><img src="../../references/source.png" alt="Package reference"><a href="../../references/source.png">Original</a><a href="../../DESIGN.md">Design</a></body></html>');
        $this->build();
        $page = file_get_contents($this->output . '/docs/brand/index.html');
        self::assertStringNotContainsString('<img', $page);
        self::assertStringContainsString('View reference in the repository: Package reference', $page);
        self::assertStringContainsString(self::REPOSITORY . '/blob/main/references/source.png', $page);
        self::assertStringContainsString('href="../../DESIGN.md"', $page);
    }

    public function testPublicationRecordsFailClosedForEveryActiveStatus(): void
    {
        $record = ['status' => 'authorized', 'scope' => 'brand-public-library', 'authorization' => 'maintainer-request', 'recorded_at' => '2026-10-05', 'evidence' => 'Maintainer requested publication.'];
        $invalid = ['not-published', 'authorized', null, [], (object) [], array_replace($record, ['scope' => 'unknown']), array_replace($record, ['status' => 'denied']), array_replace($record, ['authorization' => 'unknown']), array_replace($record, ['evidence' => '']), array_replace($record, ['recorded_at' => null]), ['scope' => 'brand-review-gallery', 'authorization' => 'maintainer-request']];
        foreach (['canonical', 'package-variant', 'reference', 'exploratory'] as $status) {
            foreach ($invalid as $index => $publication) {
                $this->asset("assets/{$status}-{$index}.png", $status);
                $this->rows[array_key_last($this->rows)]['publication'] = $publication;
            }
        }
        $this->asset('assets/authorized.png', 'canonical');
        $this->rows[array_key_last($this->rows)]['publication'] = $record;
        $this->manifest();
        $this->build();
        $published = json_decode(file_get_contents($this->output . '/assets/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['assets/current.png', 'assets/authorized.png'], array_column($published['assets'], 'path'));
        self::assertSame($record, $published['assets'][1]['publication']);
    }

    public function testOnlyExplicitlyAuthorizedCurrentEcosystemReferencesArePublished(): void
    {
        foreach (['current', 'historical', 'denied', 'gallery'] as $name) {
            $this->asset("references/ecosystem/{$name}.png", 'reference');
            if ($name !== 'historical') {
                $this->rows[array_key_last($this->rows)]['publication'] = ['scope' => $name === 'gallery' ? 'brand-review-gallery' : 'brand-public-library', 'status' => $name === 'denied' ? 'denied' : 'authorized', 'authorization' => 'maintainer-request', 'recorded_at' => '2026-10-05', 'evidence' => 'Maintainer requested contextual public examples.'];
            }
        }
        $this->asset('references/unrelated.png', 'reference');
        $this->rows[array_key_last($this->rows)]['publication'] = $this->rows[1]['publication'];
        $this->manifest();
        $this->write('docs/brand/index.html', '<html><body><img src="../../references/ecosystem/current.png" alt="Current contextual example"><img src="../../references/ecosystem/historical.png" alt="Historical source"></body></html>');
        $this->build();
        self::assertFileExists($this->output . '/references/ecosystem/current.png');
        foreach (['references/ecosystem/historical.png', 'references/ecosystem/denied.png', 'references/ecosystem/gallery.png', 'references/unrelated.png'] as $path) {
            self::assertFileDoesNotExist($this->output . '/' . $path);
        }
        $page = file_get_contents($this->output . '/docs/brand/index.html');
        self::assertStringContainsString('src="../../references/ecosystem/current.png"', $page);
        self::assertStringContainsString('View reference in the repository: Historical source', $page);
    }

    public function testAwaitingReviewRequiresExplicitUnselectedGalleryAuthorization(): void
    {
        $record = ['scope' => 'brand-review-gallery', 'authorization' => 'maintainer-request', 'recorded_at' => '2026-10-05', 'evidence' => 'Requested gallery.', 'canonical_selection' => false];
        foreach (['draft', 'gallery', 'unconfirmed', 'string', 'selected', 'canonical'] as $name) {
            $this->asset("assets/{$name}.png", $name === 'canonical' ? 'canonical' : 'exploratory');
            $row = array_key_last($this->rows);
            $this->rows[$row]['review_status'] = 'awaiting-review';
            if ($name !== 'draft') {
                $this->rows[$row]['publication'] = match ($name) {
                    'unconfirmed' => ['scope' => 'brand-review-gallery'],
                    'string' => 'local draft',
                    'selected' => array_replace($record, ['canonical_selection' => true]),
                    default => $record,
                };
            }
        }
        $this->manifest();
        $this->build();
        foreach (['draft', 'unconfirmed', 'string', 'selected', 'canonical'] as $name) {
            self::assertFileDoesNotExist("{$this->output}/assets/{$name}.png");
        }
        self::assertFileExists($this->output . '/assets/gallery.png');
    }

    public function testFilteredProvenanceRetainsLineageAndClosesRegistry(): void
    {
        $this->asset('references/source.png', 'reference');
        $this->asset('assets/legacy.png', 'legacy');
        $this->asset('assets/derived.png', 'canonical');
        $row = array_key_last($this->rows);
        $this->rows[$row]['rights'] = ['status' => 'unconfirmed', 'note' => 'Synthetic fixture'];
        $this->rows[$row]['provenance'] = ['source_ids' => ['assets/current.png', 'references/source.png', 'archived-origin', 'assets/legacy.png'], 'repository_source_ids' => ['already-external'], 'raw_source_sha256' => str_repeat('a', 64), 'created_at' => '2026-10-04'];
        $this->manifest(['archived_sources' => [['id' => 'archived-origin', 'path' => 'backup/origin.png', 'provenance' => ['source_ids' => ['references/source.png'], 'raw_source_sha256' => str_repeat('b', 64)]]]]);
        $original = file_get_contents($this->root . '/assets/manifest.json');
        $ref = str_repeat('a', 40);
        $this->build($ref);
        $published = json_decode(file_get_contents($this->output . '/assets/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $derived = $published['assets'][1];
        self::assertSame(['assets/current.png', 'archived-origin'], $derived['provenance']['source_ids']);
        self::assertSame(['already-external', 'references/source.png', 'assets/legacy.png'], $derived['provenance']['repository_source_ids']);
        self::assertSame(self::REPOSITORY . '/blob/' . $ref . '/assets/manifest.json', $derived['provenance']['repository_manifest']);
        self::assertSame($this->rows[$row]['rights'], $derived['rights']);
        self::assertSame($this->rows[$row]['sha256'], $derived['sha256']);
        self::assertSame(str_repeat('a', 64), $derived['provenance']['raw_source_sha256']);
        self::assertSame([], $published['archived_sources'][0]['provenance']['source_ids']);
        self::assertSame(['references/source.png'], $published['archived_sources'][0]['provenance']['repository_source_ids']);
        self::assertSame($original, file_get_contents($this->root . '/assets/manifest.json'));
        self::assertDirectoryDoesNotExist($this->output . '/references');
        self::assertDirectoryDoesNotExist($this->output . '/backup');
    }

    public function testResourceFailuresOccurBeforeOutputAndAllowExternalNavigation(): void
    {
        foreach (['<img src="https://example.invalid/art.png">', '<img src=//example.invalid/art.png>', '<source srcset="../../assets/current.png 1x, https://example.invalid/art.png 2x">', '<script src="https://example.invalid/code.js"></script>', '<link rel="stylesheet" href="https://example.invalid/style.css">', '<video poster="https://example.invalid/poster.png"></video>', '<object data="https://example.invalid/content.svg"></object>', '<img src="data:image/png;base64,aGVsbG8=">', '<div style="background:url(https://example.invalid/art.png)"></div>', '<style>body{background:url(https://example.invalid/art.png)}</style>', '<svg><image xlink:href="https://example.invalid/art.png"/></svg>', '<body background="https://example.invalid/tracker.png">', '<table background="https://example.invalid/tracker.png"></table>'] as $element) {
            $this->write('docs/brand/index.html', '<html><body>' . $element . '</body></html>');
            $this->rejects('External resource');
        }
        foreach (['<img src="../../assets/missing.png">', '<body background="../../assets/missing.png">', '<link rel=stylesheet href=../../references/unpublished.css>'] as $element) {
            $this->write('docs/brand/index.html', '<html>' . $element . '</html>');
            $this->rejects('Unpublished resource');
        }
        $this->write('docs/brand/index.html', '<html><body background="../../assets/current.png"><a href="https://example.invalid/guide">Guide</a></body></html>');
        $this->build();
        self::assertStringContainsString('href="https://example.invalid/guide"', file_get_contents($this->output . '/docs/brand/index.html'));
    }

    public function testOutputValidationRejectsExternalResourcesAndCss(): void
    {
        $this->build();
        foreach (['<img src="https://example.invalid/art.png">', '<link rel=stylesheet href="https://example.invalid/style.css">', '<source srcset="https://example.invalid/art.png 1x">', '<svg><image xlink:href="https://example.invalid/art.png"/></svg>', '<div style="background:url(https://example.invalid/art.png)"></div>', '<style>body{background:url(https://example.invalid/art.png)}</style>', '<body background="https://example.invalid/tracker.png">'] as $element) {
            file_put_contents($this->output . '/docs/brand/index.html', '<html>' . $element . '</html>');
            $this->rejects('External resource', fn () => BrandPages::checkOutput($this->output), false);
        }
        file_put_contents($this->output . '/docs/brand/index.html', '<html></html>');
        file_put_contents($this->output . '/assets/external.css', 'body{background:url("https://example.invalid/art.png")}');
        $this->rejects('External resource', fn () => BrandPages::checkOutput($this->output), false);
    }

    public function testNestedDocumentsAndAlternateBasesAreRejectedBothBeforeAndAfterBuild(): void
    {
        foreach (['<iframe srcdoc="&lt;img src=&quot;https://example.invalid/art.png&quot;&gt;"></iframe>', '<base href="https://example.invalid/">', '<svg xml:base="https://example.invalid/"><image href="art.png"/></svg>'] as $element) {
            $this->write('docs/brand/index.html', '<html>' . $element . '</html>');
            $this->rejects('Embedded documents and alternate bases');
            mkdir($this->output);
            file_put_contents($this->output . '/index.html', '<html>' . $element . '</html>');
            $this->rejects('Embedded documents and alternate bases', fn () => BrandPages::checkOutput($this->output), false);
            unlink($this->output . '/index.html');
            rmdir($this->output);
        }
    }

    public function testCssDependenciesMustBeSelectedAndSupported(): void
    {
        foreach ([['body{background:url(https://example.invalid/art.png)}', 'External resource'], ['body{background:url(../../references/art.png)}', 'Unpublished resource'], ['@import "https://example.invalid/style.css";', 'CSS imports'], ['body{background:image-set("https://example.invalid/art.png" 1x)}', 'image-set'], ['body{fill:u\\72l(https://example.invalid/paint.svg#x)}', 'CSS escapes']] as [$css, $error]) {
            $this->rows = array_values(array_filter($this->rows, fn ($row) => $row['path'] !== 'assets/styles/current.css'));
            $this->asset('assets/styles/current.css', 'canonical');
            $path = $this->write('assets/styles/current.css', $css);
            $this->rows[array_key_last($this->rows)]['sha256'] = hash_file('sha256', $path);
            $this->manifest();
            $this->rejects($error);
        }
        $path = $this->write('assets/styles/current.css', 'body{background:url(../current.png)}');
        $this->rows[array_key_last($this->rows)]['sha256'] = hash_file('sha256', $path);
        $this->manifest();
        $this->write('docs/brand/index.html', '<html><head><link rel="stylesheet" href="../../assets/styles/current.css"></head></html>');
        $this->build();
        self::assertFileExists($this->output . '/assets/styles/current.css');
    }

    public function testMarkdownBadgesBecomeLinksWhileFencesAutolinksAndScriptRemainLiteral(): void
    {
        $snippet = "```html\n<link rel=\"stylesheet\" href=\"https://example.invalid/style.css\">\n```";
        $autolink = '<https://example.invalid/Guide>';
        $this->write('README.md', '# Readme' . "\n\n" . '<a href="https://example.invalid/repository"><img src="https://example.invalid/badge.svg" alt="Framework repository"></a>' . "\n\n![Remote diagram](https://example.invalid/diagram.png)\n\n{$autolink}\n\n{$snippet}\n");
        $literal = 'const example = \'<img src="https://example.invalid/art.png">\';';
        $this->write('docs/brand/index.html', '<html><script>' . $literal . '</script></html>');
        $this->build();
        $source = file_get_contents($this->output . '/README.md');
        self::assertStringContainsString('<a href="https://example.invalid/repository">Framework repository</a>', $source);
        self::assertStringContainsString('[View external image: Remote diagram](https://example.invalid/diagram.png)', $source);
        self::assertStringContainsString($snippet, $source);
        self::assertStringContainsString($autolink, $source);
        self::assertStringNotContainsString('<img src="https://example.invalid/badge', $source);
        self::assertStringContainsString($literal, file_get_contents($this->output . '/docs/brand/index.html'));
    }

    public function testHashDriftRejectsBeforeWriting(): void
    {
        $this->write('assets/current.png', 'changed fixture');
        $this->rejects('changed after cataloging');
    }

    public function testRawTextOnlyEndsAtItsMatchingClosingTag(): void
    {
        $script = '<script>const demo = "</style><audio src=https://example.invalid/demo.mp3>";</script>';
        $style = '<style>.demo::before {content:"</script><a href=private/unpublished.mp3>"}</style>';
        $this->write('README.md', $script . "\n\n" . $style);
        $this->build();
        self::assertSame($script . "\n\n" . $style, file_get_contents($this->output . '/README.md'));
    }

    public function testAllMarkdownHtmlResourcesAreValidatedAndSvgNestingIsPreserved(): void
    {
        foreach (['<audio src="https://example.invalid/audio.mp3"></audio>', '<embed src="https://example.invalid/document.pdf">', '<span style="background:url(https://example.invalid/art.png)">Text</span>', '<table background="https://example.invalid/tracker.png"><tr><td>Text</td></tr></table>', '<svg><image href="https://example.invalid/art.png"/></svg>'] as $element) {
            $this->write('README.md', '# Readme' . "\n\n" . $element);
            $this->rejects('External resource');
        }
        $this->write('README.md', '<audio src="private/unpublished.mp3"></audio>');
        $this->rejects('Unpublished resource');
        $svg = '<svg><image href="assets/current.png"/></svg>';
        $this->write('README.md', '# Readme' . "\n\n" . $svg . "\n");
        $this->build();
        self::assertStringContainsString($svg, file_get_contents($this->output . '/README.md'));
    }

    public function testSymlinksCannotPublishExternalFiles(): void
    {
        unlink($this->root . '/assets/current.png');
        file_put_contents($this->temporary . '/external.txt', 'external');
        symlink($this->temporary . '/external.txt', $this->root . '/assets/current.png');
        $this->rejects('Symlinks');
    }

    public function testUnsafePrivateHiddenPathsRejectBeforeWriting(): void
    {
        foreach (['assets/../private/photo.jpg' => 'Unsafe path', 'assets/private/photo.png' => 'Private/archive', 'assets/.hidden.png' => 'Hidden source'] as $path => $error) {
            $this->rows = array_slice($this->rows, 0, 1);
            if (!str_contains($path, '..')) {
                $this->asset($path, 'canonical');
            } else {
                $this->rows[0]['path'] = $path;
            }
            $this->manifest();
            $this->rejects($error);
            $this->rows[0]['path'] = 'assets/current.png';
        }
    }

    public function testExistingOutputsAndCheckoutDirectoriesAreNeverOverwritten(): void
    {
        mkdir($this->output);
        file_put_contents($this->output . '/keep.txt', 'keep fixture');
        $this->rejects('nonempty', beforeOutput: false);
        self::assertSame('keep fixture', file_get_contents($this->output . '/keep.txt'));
        $this->rejects('only the _site', fn () => BrandPages::build($this->root, $this->root . '/assets/empty', self::REPOSITORY, 'main'), false);
    }

    public function testBrokenAnchorsFailBeforeOutput(): void
    {
        $this->write('docs/brand/index.html', '<html><body id="main"><a href="#missing">Missing</a></body></html>');
        $this->rejects('Missing anchor');
    }

    public function testEveryPublishedJsonIsStrictlyValidatedBeforeOutput(): void
    {
        foreach (['{not valid json', '{"output":{"sha256":"first","sha256":"second"}}', '{"value":NaN}', '{"value":Infinity}', '"scalar"'] as $payload) {
            $this->write('docs/brand/dash-generation.json', $payload);
            $this->rejects($payload === '"scalar"' ? 'Public JSON must' : 'Invalid public JSON');
        }
        $payload = '{"output":{"sha256":"recorded"},"references":[{"id":"Dash"}]}';
        $this->write('docs/brand/dash-generation.json', $payload);
        $this->build();
        self::assertSame($payload, file_get_contents($this->output . '/docs/brand/dash-generation.json'));
    }
}
