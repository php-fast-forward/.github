<?php

declare(strict_types=1);

use PhpFastForward\Brand\DesignExports;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DesignExportsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private function fixture(): array
    {
        return [
            'version' => 'alpha', 'name' => 'Fixture', 'description' => 'Isolated design fixture',
            'colors' => ['primary' => '#6d28d9', 'white' => '#ffffff', 'ink' => '#142033'],
            'typography' => ['body' => ['fontFamily' => 'system-ui, "Family, One", "Segoe UI", sans-serif', 'fontSize' => '1rem', 'fontWeight' => 400, 'lineHeight' => 1.6, 'letterSpacing' => '0px'], 'h1' => ['fontFamily' => 'system-ui, sans-serif', 'fontSize' => '3rem', 'fontWeight' => 750, 'lineHeight' => '1.1', 'letterSpacing' => '-0.03em']],
            'rounded' => ['sm' => '6px'], 'spacing' => ['md' => '16px'],
            'components' => ['page' => ['backgroundColor' => '{colors.white}', 'textColor' => '{colors.ink}', 'typography' => '{typography.body}', 'padding' => '{spacing.md}']],
        ];
    }

    private function markdown(array $data): string
    {
        return "---\n" . Yaml::dump($data, 6) . "---\n\n## Overview\n## Colors\n## Typography\n## Layout\n## Elevation & Depth\n## Shapes\n## Components\n## Do's and Don'ts\n";
    }

    public function testNativeExportsPreserveEveryDeliveredValueAndByte(): void
    {
        $outputs = DesignExports::generate(file_get_contents(self::ROOT . '/DESIGN.md'));
        foreach ($outputs as $name => $output) {
            self::assertSame(file_get_contents(self::ROOT . '/assets/tokens/' . $name), $output['value'], $name);
        }
        DesignExports::verifyBrowserAdapter($outputs['theme.css']['value'], file_get_contents(self::ROOT . '/assets/styles/documentation.css'));
        self::assertCount(3, $outputs);
    }

    public function testTypographyAndTailwindPreserveCompleteFallbacksAndRelativeTracking(): void
    {
        $outputs = DesignExports::generate($this->markdown($this->fixture()));
        $dtcg = json_decode($outputs['tokens.json']['value'], true, flags: JSON_THROW_ON_ERROR);
        $value = $dtcg['typography']['h1']['$value'];
        self::assertSame(['fontFamily', 'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight'], array_keys($value));
        self::assertSame(['value' => -0.09, 'unit' => 'rem'], $value['letterSpacing']);
        self::assertSame(1.6, $dtcg['typography']['body']['$value']['lineHeight']);
        $tailwind = json_decode($outputs['tailwind.theme.json']['value'], true, flags: JSON_THROW_ON_ERROR)['theme']['extend'];
        self::assertSame(['system-ui', 'Family, One', 'Segoe UI', 'sans-serif'], $tailwind['fontFamily']['body']);
        self::assertSame('1.6', $tailwind['fontSize']['body'][1]['lineHeight']);
        self::assertStringContainsString('--font-body: system-ui, "Family, One", "Segoe UI", sans-serif;', $outputs['theme.css']['value']);
        self::assertStringContainsString('--text-body--line-height: 1.6;', $outputs['theme.css']['value']);
        self::assertStringContainsString('--text-h1--letter-spacing: -0.03em;', $outputs['theme.css']['value']);
    }

    #[DataProvider('invalidSources')]
    public function testSchemaAndReferenceFailuresRejectBeforeExport(string $case, string $error): void
    {
        $data = $this->fixture();
        if ($case === 'line-height') {
            unset($data['typography']['body']['lineHeight']);
        }
        match ($case) {
            'line-height' => null,
            'size-unit' => $data['typography']['body']['fontSize'] = '1em',
            'line-unit' => $data['typography']['body']['lineHeight'] = '24px',
            'color' => $data['colors']['primary'] = '#nothex',
            'unknown' => $data['typography']['body']['fontSzie'] = '1rem',
            'broken' => $data['components']['page']['padding'] = '{spacing.missing}',
            'cycle' => $data['colors']['primary'] = '{colors.primary}',
            'list' => $data['spacing'] = ['16px'],
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($error);
        DesignExports::generate($this->markdown($data));
    }

    public static function invalidSources(): array
    {
        return [['line-height', 'missing lineHeight'], ['size-unit', 'Unsupported dimension'], ['line-unit', 'Invalid unitless lineHeight'], ['color', 'Unsupported color'], ['unknown', 'Unknown property'], ['broken', 'Broken token reference'], ['cycle', 'Cyclic'], ['list', 'token mapping']];
    }

    public function testMissingHeadingsAndDuplicateYamlKeysReject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or out-of-order section');
        DesignExports::generate(str_replace('## Shapes', '## Unrelated', $this->markdown($this->fixture())));
    }

    public function testDuplicateYamlKeysRejectWithoutSilentlyChoosingAValue(): void
    {
        $this->expectException(\Symfony\Component\Yaml\Exception\ParseException::class);
        DesignExports::generate(str_replace('version: alpha', "version: alpha\nversion: alpha", $this->markdown($this->fixture())));
    }

    public function testContrastResolvesReferencesAndHonorsLargeTextThreshold(): void
    {
        $data = $this->fixture();
        $data['colors']['ink'] = '#949494';
        $data['components']['page']['typography'] = '{typography.h1}';
        self::assertCount(3, DesignExports::generate($this->markdown($data)));
        $data['components']['page']['typography'] = '{typography.body}';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Text contrast');
        DesignExports::generate($this->markdown($data));
    }

    public function testFontFamilyParserPreservesQuotedCommasAndRejectsMalformedStacks(): void
    {
        self::assertSame(['system-ui', 'Family, One', 'Segoe UI', 'sans-serif'], DesignExports::fontFamilies('system-ui, "Family, One", \'Segoe UI\', sans-serif'));
        $this->expectException(RuntimeException::class);
        DesignExports::fontFamilies('system-ui,,sans-serif');
    }

    public function testAdapterAcceptsUnconditionalLayersAndIgnoresCommentedObsoleteTokens(): void
    {
        DesignExports::verifyBrowserAdapter('@theme { --font-body: system-ui, "Segoe UI", sans-serif; --leading-body: 1.6; }', '@layer tokens; @layer tokens { :root { --font-body: system-ui, Segoe UI, sans-serif; --leading-body: 1.6; --doc-local: #aaa; /* --color-obsolete: red; */ } }');
        $this->addToAssertionCount(1);
    }

    #[DataProvider('badAdapters')]
    public function testAdapterRejectsInactiveDuplicatesObsoleteAndStaleTypes(string $adapter, string $error): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($error);
        DesignExports::verifyBrowserAdapter('@theme { --leading-body: 1.6; }', $adapter);
    }

    public static function badAdapters(): array
    {
        return [
            ['/* :root { --leading-body: 1.6; } */', 'stale'],
            ['.unused { --leading-body: 1.6; }', 'active top-level :root'],
            ['@media print { :root { --leading-body: 1.6; } }', 'active top-level :root'],
            [':root { --leading-body: "1.6"; }', 'stale'],
            [':root { --leading-body: 1.6; --leading-body: 1.6; }', 'duplicates'],
            [':root { --leading-body: 1.6; } :root { --leading-body: 1.6; }', 'duplicates'],
            [':root { --leading-body: 1.6; --leading-obsolete: 1.6; }', 'obsolete'],
            [':root { --leading-body: 1.6; } .unused { --leading-body: 1.6; }', 'active top-level :root'],
        ];
    }

    public function testWholeQuotedFontStackIsStale(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stale');
        DesignExports::verifyBrowserAdapter('@theme { --font-body: system-ui, sans-serif; }', ':root { --font-body: "system-ui, sans-serif"; }');
    }

    #[DataProvider('tokenNamespaces')]
    public function testRemovedAndRenamedTokensRejectWhileLocalVariablesRemainAllowed(string $namespace): void
    {
        DesignExports::verifyBrowserAdapter("@theme { --{$namespace}-current: 1; }", ":root { --{$namespace}-current: 1; --doc-local: #aaa; --brand-local: #bbb; }");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('obsolete token');
        DesignExports::verifyBrowserAdapter("@theme { --{$namespace}-current: 1; }", ":root { --{$namespace}-current: 1; --{$namespace}-obsolete: 1; }");
    }

    public static function tokenNamespaces(): array
    {
        return array_map(fn ($name) => [$name], ['color', 'font', 'font-weight', 'text', 'leading', 'tracking', 'spacing', 'radius']);
    }

    public function testMetadataRecordsSourceAndActualOutputBytes(): void
    {
        $outputs = DesignExports::generate(file_get_contents(self::ROOT . '/DESIGN.md'));
        $metadata = DesignExports::metadata(self::ROOT, $outputs);
        self::assertSame(hash_file('sha256', self::ROOT . '/DESIGN.md'), $metadata['source_sha256']);
        self::assertSame(hash_file('sha256', self::ROOT . '/scripts/DesignExports.php'), $metadata['normalization_sha256']);
        self::assertSame('php scripts/export-design.php', $metadata['generator']);
        foreach ($metadata['outputs'] as $row) {
            self::assertSame(hash('sha256', $outputs[$row['path']]['value']), $row['sha256']);
        }
    }
}
