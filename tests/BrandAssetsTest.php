<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Exercise the public PHP CLI with synthetic assets in isolated repositories. */
final class BrandAssetsTest extends TestCase
{
    private string $root;
    private array $assets = [];
    private mixed $archivedSources;
    private bool $hasArchives = false;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/brand-assets-' . bin2hex(random_bytes(12));
        mkdir($this->root . '/assets', 0700, true);
        $this->assets = [];
        $this->hasArchives = false;
        $this->archivedSources = [];
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    private static function chunk(string $kind, string $payload): string
    {
        return pack('N', strlen($payload)) . $kind . $payload . pack('N', crc32($kind . $payload));
    }

    private static function scanlines(int $width, int $height, string $pixels, int $depth = 8, int $color = 6, int $interlace = 0, ?array $idatParts = null, array $beforeIdat = [], array $afterIdat = []): string
    {
        $header = pack('NNCCCCC', $width, $height, $depth, $color, 0, 0, $interlace);
        $parts = $idatParts ?? [gzcompress($pixels)];
        return "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', $header) . implode('', $beforeIdat) . implode('', array_map(static fn (string $part): string => self::chunk('IDAT', $part), $parts)) . implode('', $afterIdat) . self::chunk('IEND', '');
    }

    private static function png(int $width = 1, int $height = 1, bool $alpha = true): string
    {
        $pixels = str_repeat("\x00" . str_repeat("\x40\x80\xc0" . ($alpha ? "\xff" : ''), $width), $height);
        return self::scanlines($width, $height, $pixels, color: $alpha ? 6 : 2);
    }

    private function write(string $relative, string $data): void
    {
        $directory = dirname($this->root . '/' . $relative);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($this->root . '/' . $relative, $data);
    }

    private function addAsset(string $relative = 'assets/mascot.png', ?string $content = null, mixed ...$overrides): stdClass
    {
        $content ??= self::png();
        $this->write($relative, $content);
        $digest = hash('sha256', $content);
        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $asset = (object) [
            'id' => 'mascot', 'path' => $relative, 'category' => 'mascot', 'status' => 'canonical',
            'role' => 'Isolated brand fixture', 'rights' => 'Original synthetic test fixture',
            'provenance' => (object) ['source' => 'isolated fixture', 'source_sha256' => $digest],
            'sha256' => $digest, 'bytes' => strlen($content), 'format' => $extension,
        ];
        if ($extension === 'png') {
            foreach (['width' => 1, 'height' => 1, 'mode' => 'RGBA', 'has_alpha' => true] as $key => $value) {
                $asset->{$key} = $value;
            }
        }
        if ($extension === 'svg') {
            $asset->embedded_raster = false;
        }
        foreach ($overrides as $key => $value) {
            $asset->{$key} = $value;
        }
        $this->assets[] = $asset;
        return $asset;
    }

    private function addArchivedSource(mixed ...$overrides): stdClass
    {
        $content = self::png();
        $digest = hash('sha256', $content);
        $source = (object) [
            'id' => 'retired-logo', 'path' => 'assets/retired-logo.png',
            'sha256' => $digest, 'bytes' => strlen($content), 'format' => 'png',
            'provenance' => (object) ['source' => 'isolated historical fixture', 'source_sha256' => $digest],
            'availability' => 'local-backup-not-distributed',
        ];
        foreach ($overrides as $key => $value) {
            $source->{$key} = $value;
        }
        $this->hasArchives = true;
        $this->archivedSources[] = $source;
        return $source;
    }

    private function runValidator(int $expectedCode, ?string $rawManifest = null): array
    {
        $manifest = ['schema_version' => 1, 'assets' => $this->assets];
        if ($this->hasArchives) {
            $manifest['archived_sources'] = $this->archivedSources;
        }
        $this->write('assets/manifest.json', $rawManifest ?? json_encode($manifest, JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/validate-brand.php', '--root', $this->root], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($process);
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + 30;
        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                self::fail('Validator exceeded the isolated fixture timeout');
            }
            usleep(1000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        self::assertSame($expectedCode, $status['exitcode'], $stdout . $stderr);
        self::assertSame('', $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expectedCode === 0, $result['valid']);
        return $result;
    }

    private function assertError(array $result, string $text): void
    {
        self::assertNotEmpty(array_filter($result['errors'], static fn (string $error): bool => str_contains($error, $text)), json_encode($result));
    }

    public function test_valid_png_and_independent_working_directory(): void
    {
        $this->addAsset();
        self::assertSame(['valid' => true, 'checked' => 1, 'errors' => []], $this->runValidator(0));
    }

    public function test_replaced_file_is_detected_by_digest_and_size(): void
    {
        $this->addAsset();
        $this->write('assets/mascot.png', self::png(width: 2));
        $result = $this->runValidator(1);
        $this->assertError($result, 'SHA-256 mismatch');
        $this->assertError($result, 'byte count mismatch');
    }

    public function test_missing_file(): void
    {
        $this->addAsset();
        unlink($this->root . '/assets/mascot.png');
        $result = $this->runValidator(1);
        $this->assertError($result, 'file is missing');
        self::assertSame(0, $result['checked']);
    }

    public function test_parent_traversal_is_rejected_before_file_read(): void
    {
        $this->addAsset(path: '../mascot.png');
        $result = $this->runValidator(1);
        $this->assertError($result, 'path must not be absolute');
        self::assertSame(0, $result['checked']);
    }

    public function test_absolute_path_is_rejected(): void
    {
        $this->addAsset(path: $this->root . '/assets/mascot.png');
        $this->assertError($this->runValidator(1), 'path must not be absolute');
    }

    public function test_symlink_traversal_is_rejected(): void
    {
        $this->addAsset();
        symlink($this->root . '/assets/mascot.png', $this->root . '/assets/linked.png');
        $this->assets[0]->path = 'assets/linked.png';
        $result = $this->runValidator(1);
        $this->assertError($result, 'path must not traverse a symlink');
        self::assertSame(0, $result['checked']);
    }

    public function test_duplicate_ids(): void
    {
        $this->addAsset();
        $this->addAsset('references/mascot.png');
        $this->assertError($this->runValidator(1), 'duplicate id');
    }

    public function test_duplicate_paths(): void
    {
        $asset = clone $this->addAsset();
        $asset->id = 'second-mascot';
        $this->assets[] = $asset;
        $this->assertError($this->runValidator(1), 'duplicate path');
    }

    public function test_archived_sources_do_not_require_files_or_a_backup(): void
    {
        $this->addArchivedSource(archive_path: 'backup/brand/retired/assets/retired-logo.png');
        self::assertSame(['valid' => true, 'checked' => 0, 'errors' => []], $this->runValidator(0));
        self::assertFileDoesNotExist($this->root . '/backup');
        self::assertFileDoesNotExist($this->root . '/assets/retired-logo.png');
    }

    public function test_historical_path_can_be_reused_by_a_new_active_asset(): void
    {
        $this->addAsset('profile/assets/banner.png');
        $this->addArchivedSource(path: 'profile/assets/banner.png', sha256: str_repeat('a', 64), bytes: 999);
        self::assertSame(1, $this->runValidator(0)['checked']);
    }

    public function test_archived_sources_are_not_read_or_parsed(): void
    {
        $this->addArchivedSource(path: 'profile/assets/old-logo.svg', format: 'svg', sha256: str_repeat('a', 64), bytes: 999);
        $this->write('profile/assets/old-logo.svg', '<svg><script>invalid retired bytes</script></svg>');
        self::assertSame(0, $this->runValidator(0)['checked']);
    }

    public function test_archive_metadata_does_not_exempt_a_lingering_managed_image(): void
    {
        $this->addArchivedSource();
        $this->write('assets/retired-logo.png', self::png());
        $this->assertError($this->runValidator(1), 'managed image has no manifest entry');
    }

    public function test_archived_source_ids_are_unique_across_both_lists(): void
    {
        $this->addAsset();
        $this->addArchivedSource(id: 'mascot');
        $this->assertError($this->runValidator(1), 'duplicate id');
        $this->archivedSources = [];
        $this->addArchivedSource();
        $this->addArchivedSource(path: 'profile/assets/old-logo.png');
        $this->assertError($this->runValidator(1), 'duplicate id');
    }

    public function test_multiple_historical_versions_can_share_a_delivery_path(): void
    {
        $this->addArchivedSource();
        $this->addArchivedSource(id: 'older-logo', sha256: str_repeat('a', 64));
        self::assertSame(0, $this->runValidator(0)['checked']);
    }

    public function test_archived_source_paths_are_lexically_safe(): void
    {
        foreach (['../retired.png', '/retired.png', 'C:/retired.png', 'assets\\retired.png', 'assets//retired.png', './assets/retired.png', "assets/retired\x00.png"] as $relative) {
            $this->archivedSources = [];
            $this->addArchivedSource(path: $relative);
            self::assertNotEmpty($this->runValidator(1)['errors']);
        }
    }

    public function test_optional_archive_location_must_be_safe(): void
    {
        $this->addArchivedSource(archive_path: '../outside.png');
        $this->assertError($this->runValidator(1), 'archive_path path must not be absolute');
    }

    public function test_archived_source_metadata_is_required_and_typed(): void
    {
        foreach ([
            ['id', '', 'id must be'], ['availability', 'distributed', 'availability must be'],
            ['sha256', 'not a digest', 'sha256 must be'], ['bytes', true, 'bytes must be'],
            ['bytes', -1, 'bytes must be'], ['format', 'svg', 'format must match'],
            ['provenance', (object) [], 'provenance.source must be'],
            ['provenance', (object) ['source' => 'fixture', 'source_sha256' => 'bad'], 'provenance.source_sha256 must be'],
        ] as [$field, $value, $message]) {
            $this->archivedSources = [];
            $source = $this->addArchivedSource();
            $source->{$field} = $value;
            $this->assertError($this->runValidator(1), $message);
        }
    }

    public function test_archived_source_list_and_records_must_have_correct_types(): void
    {
        foreach ([(object) ['retired-logo' => (object) []], ['retired-logo'], null] as $value) {
            $this->hasArchives = true;
            $this->archivedSources = $value;
            self::assertNotEmpty($this->runValidator(1)['errors']);
        }
    }

    public function test_source_ids_resolve_active_and_archived_sources_without_io(): void
    {
        $this->addAsset();
        $active = $this->addAsset('assets/new-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', id: 'new-logo');
        $archive = $this->addArchivedSource();
        $active->provenance->source_ids = ['retired-logo'];
        $archive->provenance->source_ids = ['mascot'];
        self::assertSame(2, $this->runValidator(0)['checked']);
    }

    public function test_source_ids_reject_unknown_or_malformed_references(): void
    {
        $active = $this->addAsset();
        foreach ([[['absent'], 'unknown source id'], ['mascot', 'source_ids must be a list'], [[(object) []], 'source id must be a nonempty string']] as [$value, $message]) {
            $active->provenance->source_ids = $value;
            $this->assertError($this->runValidator(1), $message);
        }
    }

    public function test_dimensions_are_read_from_png_header(): void
    {
        $this->addAsset(width: 99);
        $this->assertError($this->runValidator(1), 'width mismatch');
    }

    public function test_transparency_and_mode_are_verified(): void
    {
        $this->addAsset(content: self::png(alpha: false));
        $result = $this->runValidator(1);
        $this->assertError($result, 'mode mismatch');
        $this->assertError($result, 'has_alpha mismatch');
    }

    public function test_png_corruption_fails_even_if_manifest_hash_is_updated(): void
    {
        $content = self::png();
        $content[29] = chr(ord($content[29]) ^ 1);
        $this->addAsset(content: $content);
        $this->assertError($this->runValidator(1), 'invalid PNG chunk checksum');
    }

    public function test_png_idat_stream_is_validated_with_correct_crc_and_hash(): void
    {
        $valid = gzcompress("\x00\x40\x80\xc0\xff");
        foreach (['', 'not a zlib stream', substr($valid, 0, -1), gzcompress('')] as $compressed) {
            $this->assets = [];
            $this->addAsset(content: self::scanlines(1, 1, '', idatParts: [$compressed]));
            $this->assertError($this->runValidator(1), 'PNG image data');
        }
    }

    public function test_png_scanline_sizes_must_match_the_header(): void
    {
        foreach (["\x00\x40\x80\xc0", "\x00\x40\x80\xc0\xff\xff"] as $pixels) {
            $this->assets = [];
            $this->addAsset(content: self::scanlines(1, 1, $pixels));
            $this->assertError($this->runValidator(1), 'PNG image data');
        }
    }

    public function test_png_filter_bytes_are_validated(): void
    {
        $this->addAsset(content: self::scanlines(1, 1, "\x05\x40\x80\xc0\xff"));
        $this->assertError($this->runValidator(1), 'invalid scanline filter');
    }

    public function test_png_accepts_all_five_filter_methods(): void
    {
        $pixels = '';
        for ($method = 0; $method < 5; ++$method) {
            $pixels .= chr($method) . "\x40\x80\xc0\xff";
        }
        $this->addAsset(content: self::scanlines(1, 5, $pixels), height: 5);
        $this->runValidator(0);
    }

    public function test_png_zlib_boundaries_can_cross_idat_chunks(): void
    {
        $stream = gzcompress("\x00\x40\x80\xc0\xff");
        $parts = ['', substr($stream, 0, 1), substr($stream, 1, 3), substr($stream, 4, -2), substr($stream, -2), ''];
        $this->addAsset(content: self::scanlines(1, 1, '', idatParts: $parts));
        $this->runValidator(0);
    }

    public function test_png_scanlines_can_cross_bounded_output_blocks(): void
    {
        $width = 16384;
        $row = str_repeat("\x40\x80\xc0\xff", $width);
        $this->addAsset(content: self::scanlines($width, 2, "\x01" . $row . "\x04" . $row), width: $width, height: 2);
        $this->runValidator(0);
    }

    public function test_png_idat_chunks_must_remain_consecutive(): void
    {
        $stream = gzcompress("\x00\x40\x80\xc0\xff");
        $header = pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0);
        $content = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', $header) . self::chunk('IDAT', substr($stream, 0, 3)) . self::chunk('tEXt', "Comment\x00test") . self::chunk('IDAT', substr($stream, 3)) . self::chunk('IEND', '');
        $this->addAsset(content: $content);
        $this->assertError($this->runValidator(1), 'IDAT chunks must be consecutive');
    }

    public function test_png_allows_unused_bytes_in_the_final_idat(): void
    {
        $stream = gzcompress("\x00\x40\x80\xc0\xff");
        $this->addAsset(content: self::scanlines(1, 1, '', idatParts: [$stream . 'unused trailing bytes']));
        $this->runValidator(0);
    }

    public function test_png_adam7_one_pixel_has_no_empty_pass_filters(): void
    {
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x40\x80\xc0\xff", interlace: 1));
        $this->runValidator(0);
    }

    public function test_png_adam7_checks_all_seven_passes(): void
    {
        // Fixed pass dimensions are independent of the production calculation.
        $shapes = [[1, 1], [1, 1], [2, 1], [2, 2], [4, 2], [4, 4], [8, 4]];
        $pixels = '';
        foreach ($shapes as [$width, $height]) {
            $pixels .= str_repeat("\x00" . str_repeat("\x40\x80\xc0\xff", $width), $height);
        }
        $this->addAsset(content: self::scanlines(8, 8, $pixels, interlace: 1), width: 8, height: 8);
        $this->runValidator(0);
        $this->assets = [];
        $this->addAsset(content: self::scanlines(8, 8, substr($pixels, 0, -1), interlace: 1), width: 8, height: 8);
        $this->assertError($this->runValidator(1), 'incorrect decompressed scanline size');
    }

    public function test_png_packed_bits_and_sixteen_bit_channels(): void
    {
        $this->addAsset(content: self::scanlines(9, 1, "\x00\xaa\x80", depth: 1, color: 0), width: 9, mode: '1', has_alpha: false);
        $this->runValidator(0);
        $this->assets = [];
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x40\x00\x80\x00\xc0\x00\xff\xff", depth: 16));
        $this->runValidator(0);
    }

    public function test_indexed_png_requires_palette_before_image_data(): void
    {
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", color: 3), mode: 'P', has_alpha: false);
        $this->assertError($this->runValidator(1), 'requires PLTE before IDAT');
        $this->assets = [];
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", color: 3, beforeIdat: [self::chunk('PLTE', "\x00\x00\x00")]), mode: 'P', has_alpha: false);
        $this->runValidator(0);
    }

    public function test_png_palette_size_and_color_type_are_checked(): void
    {
        foreach ([
            ['', 3, 8, 'invalid PNG PLTE'], ["\x00\x00", 3, 8, 'invalid PNG PLTE'],
            [str_repeat("\x00", 771), 3, 8, 'invalid PNG PLTE'],
            [str_repeat("\x00", 9), 3, 1, 'exceeds the indexed bit-depth range'],
            [str_repeat("\x00", 3), 0, 8, 'invalid PNG PLTE'],
        ] as [$palette, $color, $depth, $message]) {
            $this->assets = [];
            $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", depth: $depth, color: $color, beforeIdat: [self::chunk('PLTE', $palette)]), mode: $color === 3 ? 'P' : 'L', has_alpha: false);
            $this->assertError($this->runValidator(1), $message);
        }
    }

    public function test_png_palette_is_unique_and_precedes_image_data(): void
    {
        $palette = self::chunk('PLTE', str_repeat("\x00", 3));
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", color: 3, beforeIdat: [$palette, $palette]), mode: 'P', has_alpha: false);
        $this->assertError($this->runValidator(1), 'PLTE must appear once before');
        $this->assets = [];
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x40\x80\xc0\xff", afterIdat: [$palette]));
        $this->assertError($this->runValidator(1), 'PLTE must appear once before');
    }

    public function test_indexed_png_reconstructs_all_five_filters_before_checking_indices(): void
    {
        // Each filtered row independently encodes the same indices 1,2,0.
        $pixels = "\x00\x01\x02\x00\x01\x01\x01\xfe\x02\x00\x00\x00\x03\x01\x01\xff\x04\x00\x00\x00";
        $this->addAsset(content: self::scanlines(3, 5, $pixels, color: 3, beforeIdat: [self::chunk('PLTE', str_repeat("\x00", 9))]), width: 3, height: 5, mode: 'P', has_alpha: false);
        $this->runValidator(0);
    }

    public function test_indexed_png_rejects_out_of_range_reconstructed_pixels(): void
    {
        // Filter bytes are in range; Sub reconstruction yields indices 2,3.
        $this->addAsset(content: self::scanlines(2, 1, "\x01\x02\x01", color: 3, beforeIdat: [self::chunk('PLTE', str_repeat("\x00", 9))]), width: 2, mode: 'P', has_alpha: false);
        $this->assertError($this->runValidator(1), 'references a missing palette entry');
    }

    public function test_indexed_png_checks_packed_pixels_without_checking_padding_bits(): void
    {
        $palette = self::chunk('PLTE', str_repeat("\x00", 9));
        // Five 2-bit pixels 0,1,2,0,1; the last six padding bits are ones.
        $this->addAsset(content: self::scanlines(5, 1, "\x00\x18\x7f", depth: 2, color: 3, beforeIdat: [$palette]), width: 5, mode: 'P', has_alpha: false);
        $this->runValidator(0);
        $this->assets = [];
        $this->addAsset(content: self::scanlines(5, 1, "\x00\x18\xff", depth: 2, color: 3, beforeIdat: [$palette]), width: 5, mode: 'P', has_alpha: false);
        $this->assertError($this->runValidator(1), 'references a missing palette entry');
    }

    public function test_indexed_png_adam7_resets_the_previous_row_at_each_pass(): void
    {
        $pixels = '';
        foreach ([[1, 1], [1, 1], [2, 1], [2, 2], [4, 2], [4, 4], [8, 4]] as [$width, $height]) {
            $pixels .= "\x02" . str_repeat("\x01", $width) . str_repeat("\x02" . str_repeat("\x00", $width), $height - 1);
        }
        $this->addAsset(content: self::scanlines(8, 8, $pixels, color: 3, interlace: 1, beforeIdat: [self::chunk('PLTE', str_repeat("\x00", 6))]), width: 8, height: 8, mode: 'P', has_alpha: false);
        $this->runValidator(0);
    }

    public function test_png_transparency_matches_palette_and_color_type(): void
    {
        $palette = self::chunk('PLTE', str_repeat("\x00", 6));
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", color: 3, beforeIdat: [$palette, self::chunk('tRNS', "\x00")]), mode: 'P', has_alpha: true);
        $this->runValidator(0);
        foreach ([
            [3, "\x00\x00", [$palette, self::chunk('tRNS', str_repeat("\x00", 3))], 'invalid PNG tRNS palette'],
            [3, "\x00\x00", [self::chunk('tRNS', "\x00"), $palette], 'invalid PNG tRNS palette'],
            [6, "\x00\x40\x80\xc0\xff", [self::chunk('tRNS', str_repeat("\x00", 6))], 'invalid PNG tRNS for'],
        ] as [$color, $pixels, $chunks, $message]) {
            $this->assets = [];
            $this->addAsset(content: self::scanlines(1, 1, $pixels, color: $color, beforeIdat: $chunks), mode: $color === 3 ? 'P' : 'RGBA');
            $this->assertError($this->runValidator(1), $message);
        }
    }

    public function test_indexed_png_rejects_empty_palette_transparency(): void
    {
        $chunks = [self::chunk('PLTE', str_repeat("\x00", 6)), self::chunk('tRNS', '')];
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x00", color: 3, beforeIdat: $chunks), mode: 'P', has_alpha: true);
        $this->assertError($this->runValidator(1), 'invalid PNG tRNS palette transparency');
    }

    public function test_png_unknown_critical_chunks_are_rejected(): void
    {
        $this->addAsset(content: self::scanlines(1, 1, "\x00\x40\x80\xc0\xff", beforeIdat: [self::chunk('ABCD', 'unsupported critical data')]));
        $this->assertError($this->runValidator(1), 'unsupported critical chunk');
    }

    public function test_raster_formats_without_decoders_fail_even_with_matching_metadata(): void
    {
        foreach (['gif' => 'gif', 'webp' => 'webp', 'avif' => 'avif', 'ico' => 'ico', 'bmp' => 'bmp', 'tif' => 'tiff', 'tiff' => 'tiff', 'jpg' => 'jpeg', 'jpeg' => 'jpeg'] as $extension => $format) {
            $this->assets = [];
            $path = 'assets/probe.' . $extension;
            $this->addAsset($path, 'not an image', format: $format, width: 1, height: 1, mode: 'RGB', has_alpha: false);
            $this->assertError($this->runValidator(1), 'active format ' . $format . ' has no decoder');
            unlink($this->root . '/' . $path);
        }
    }

    public function test_jpeg_headers_and_garbage_entropy_do_not_pass_as_valid_pixels(): void
    {
        $frame = "\xff\xc0" . pack('n', 11) . "\x08\x00\x01\x00\x01\x01\x01\x11\x00";
        $scan = "\xff\xda" . pack('n', 8) . "\x01\x01\x00\x00\x3f\x00";
        $this->addAsset('assets/probe.jpg', "\xff\xd8" . $frame . $scan . 'not compressed pixels' . "\xff\xd9", format: 'jpeg', width: 1, height: 1, mode: 'L', has_alpha: false);
        $this->assertError($this->runValidator(1), 'active format jpeg has no decoder');
    }

    public function test_archived_formats_without_active_decoders_remain_metadata_only(): void
    {
        $this->addArchivedSource(path: 'references/historical.jpg', format: 'jpeg');
        self::assertSame(0, $this->runValidator(0)['checked']);
        self::assertFileDoesNotExist($this->root . '/references/historical.jpg');
    }

    public function test_repository_authored_self_source_must_match_current_file_bytes(): void
    {
        $asset = $this->addAsset('assets/style.css', ".fixture { color: #142033; }\n", format: 'css');
        $asset->provenance = (object) ['source' => 'repository-authored:assets/style.css', 'source_sha256' => str_repeat('a', 64)];
        $this->assertError($this->runValidator(1), 'self-source SHA-256 must match');
        $asset->provenance->source_commit = str_repeat('b', 40);
        $this->assertError($this->runValidator(1), 'self-source SHA-256 must match');
        $asset->provenance->source_sha256 = $asset->sha256;
        $this->runValidator(0);
    }

    public function test_derived_generated_and_archived_sources_can_reference_other_bytes(): void
    {
        $asset = $this->addAsset();
        foreach (['derived:earlier-master', 'generated:isolated-generator'] as $source) {
            $asset->provenance = (object) ['source' => $source, 'source_sha256' => str_repeat('c', 64)];
            $this->runValidator(0);
        }
        unlink($this->root . '/' . $asset->path);
        $this->assets = [];
        $this->addArchivedSource(provenance: (object) ['source' => 'repository-authored:assets/retired-logo.png', 'source_sha256' => str_repeat('d', 64)]);
        self::assertSame(0, $this->runValidator(0)['checked']);
    }

    public function test_png_huge_dimensions_fail_before_unbounded_inflation(): void
    {
        $this->addAsset(content: self::scanlines(2 ** 30, 1, "\x00\x40\x80\xc0\xff"), width: 2 ** 30);
        $this->assertError($this->runValidator(1), 'decoded-size limit');
    }

    public function test_png_inflation_cannot_exceed_the_expected_size(): void
    {
        $this->addAsset(content: self::scanlines(1, 1, str_repeat("\x00", 1024 * 1024)));
        $this->assertError($this->runValidator(1), 'excess decompressed scanline bytes');
    }

    public function test_raster_metadata_is_required(): void
    {
        foreach (['width', 'height', 'mode', 'has_alpha'] as $field) {
            $this->assets = [];
            $asset = $this->addAsset();
            unset($asset->{$field});
            $this->assertError($this->runValidator(1), $field . ' must be');
        }
    }

    public function test_all_assets_require_role_and_rights(): void
    {
        foreach (['role', 'rights'] as $field) {
            $this->assets = [];
            $asset = $this->addAsset();
            unset($asset->{$field});
            $this->assertError($this->runValidator(1), $field . ' must be a nonempty string');
        }
    }

    public function test_svg_embedded_raster_metadata_is_required(): void
    {
        $asset = $this->addAsset('assets/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        unset($asset->embedded_raster);
        $this->assertError($this->runValidator(1), 'embedded_raster must be a boolean');
    }

    public function test_unlisted_managed_images_are_detected(): void
    {
        $this->addAsset();
        $this->write('references/forgotten.png', self::png());
        $this->assertError($this->runValidator(1), 'references/forgotten.png: managed image has no manifest entry');
    }

    public function test_untracked_profile_images_are_outside_managed_inventory(): void
    {
        $this->addAsset();
        $this->write('profile/assets/github-social-preview.jpg', 'untracked preview');
        $this->runValidator(0);
    }

    public function test_explicit_profile_assets_are_verified(): void
    {
        $this->addAsset('profile/assets/logo.png');
        $this->runValidator(0);
    }

    public function test_safe_vector_svg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"/></defs><path fill="url(#g)" d="M0 0L1 1"/></svg>';
        $this->addAsset('assets/logo.svg', $svg);
        $this->runValidator(0);
    }

    public function test_svg_root_must_use_svg_namespace(): void
    {
        foreach (['<svg/>', '<svg xmlns="https://example.invalid/not-svg"/>'] as $svg) {
            $this->assets = [];
            $this->addAsset('assets/logo.svg', $svg);
            $this->assertError($this->runValidator(1), 'SVG must have an svg root element in the SVG namespace');
        }
    }

    public function test_svg_css_escapes_in_presentation_attributes_are_rejected(): void
    {
        foreach ([
            ['fill', 'u\\72l(https://example.invalid/paint.svg#x)'],
            ['stroke', '\\75rl(https://example.invalid/paint.svg#x)'],
            ['filter', 'u\\000072l(https://example.invalid/filter.svg#x)'],
        ] as [$attribute, $value]) {
            $this->assets = [];
            $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path ' . $attribute . '="' . $value . '" d="M0 0L1 1"/></svg>';
            $this->addAsset('assets/logo.svg', $svg);
            $this->assertError($this->runValidator(1), 'SVG CSS escapes are not permitted');
        }
    }

    public function test_unsafe_svg_elements_and_attributes(): void
    {
        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.invalid/logo.png"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><style>@font-face {src:url(https://example.invalid/font.woff)}</style></svg>',
            '<!DOCTYPE svg [<!ENTITY external SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&external;</svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image id="i" href="#local"/><set href="#i" attributeName="href" to="https://example.invalid/logo.png"/></svg>',
        ] as $svg) {
            $this->assets = [];
            $this->addAsset('assets/logo.svg', $svg);
            self::assertNotEmpty($this->runValidator(1)['errors']);
        }
    }

    public function test_legacy_embedded_raster_wrapper_is_explicit(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,' . base64_encode(self::png()) . '"/></svg>';
        $this->addAsset('assets/legacy.svg', $svg, status: 'legacy', embedded_raster: true);
        $this->runValidator(0);
        $this->assets[0]->status = 'canonical';
        $this->assertError($this->runValidator(1), 'embedded raster SVG wrappers must have legacy status');
    }

    public function test_nested_svg_data_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zy8+"/></svg>';
        $this->addAsset('assets/legacy.svg', $svg, status: 'legacy', embedded_raster: true);
        $this->assertError($this->runValidator(1), 'only image/png is supported');
    }

    public function test_svg_embedded_png_pixels_are_validated_beyond_nonempty_base64(): void
    {
        $header = pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0);
        $invalid = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', $header) . self::chunk('IDAT', 'not zlib') . self::chunk('IEND', '');
        foreach (['not an image', $invalid] as $payload) {
            $this->assets = [];
            $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,' . base64_encode($payload) . '"/></svg>';
            $this->addAsset('assets/legacy.svg', $svg, status: 'legacy', embedded_raster: true);
            $this->assertError($this->runValidator(1), 'SVG embedded PNG is invalid');
        }
    }

    public function test_svg_embedded_raster_mimes_without_decoders_are_rejected(): void
    {
        foreach (['image/jpeg', 'image/gif', 'image/webp', 'image/bmp'] as $mime) {
            $this->assets = [];
            $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:' . $mime . ';base64,' . base64_encode('unvalidated raster bytes') . '"/></svg>';
            $this->addAsset('assets/legacy.svg', $svg, status: 'legacy', embedded_raster: true);
            $this->assertError($this->runValidator(1), 'SVG embedded raster format has no decoder');
        }
    }

    public function test_svg_embedded_classification_matches_contents(): void
    {
        $this->addAsset('assets/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', embedded_raster: true);
        $this->assertError($this->runValidator(1), 'embedded_raster must match');
    }

    public function test_invalid_manifest_metadata_is_reported(): void
    {
        $this->addAsset(bytes: true, status: 'approved', provenance: (object) ['source' => 'fixture', 'source_sha256' => 'unknown']);
        $result = $this->runValidator(1);
        $this->assertError($result, 'bytes must be a nonnegative integer');
        $this->assertError($result, 'invalid status');
        $this->assertError($result, 'provenance.source_sha256');
    }

    public function test_invalid_status_type_has_a_json_error(): void
    {
        $this->addAsset(status: []);
        $this->assertError($this->runValidator(1), 'invalid status');
    }

    public function test_utf16_svg_cannot_bypass_entity_restrictions(): void
    {
        $xml = '<!DOCTYPE svg [<!ENTITY x "expanded">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>';
        $content = "\xff\xfe" . implode('', array_map(static fn (string $character): string => $character . "\x00", str_split($xml)));
        $this->addAsset('assets/logo.svg', $content);
        $this->assertError($this->runValidator(1), 'SVG must use UTF-8 XML');
    }

    public function test_historical_svg_doctype_is_permitted_only_for_legacy(): void
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg"/>';
        $this->addAsset('assets/historical.svg', $svg, status: 'legacy');
        $this->runValidator(0);
        $this->assets[0]->status = 'canonical';
        $this->assertError($this->runValidator(1), 'SVG DTDs and entities are not permitted');
    }

    public function test_historical_doctype_cannot_include_internal_entities(): void
    {
        $svg = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" [<!ENTITY remote SYSTEM "https://example.invalid/entity">]><svg xmlns="http://www.w3.org/2000/svg">&remote;</svg>';
        $this->addAsset('assets/historical.svg', $svg, status: 'legacy');
        $this->assertError($this->runValidator(1), 'SVG DTDs and entities are not permitted');
    }

    public function test_svg_external_stylesheet_instruction_is_rejected(): void
    {
        $svg = '<?xml-stylesheet type="text/css" href="https://example.invalid/remote.css"?><svg xmlns="http://www.w3.org/2000/svg"/>';
        $this->addAsset('assets/logo.svg', $svg);
        $this->assertError($this->runValidator(1), 'SVG processing instructions are not permitted');
    }

    public function test_cataloged_css_and_json_sidecars(): void
    {
        $this->addAsset('references/icons/sprite.png');
        $this->addAsset('references/icons/sprite.css', ':root {--sprite: url("./sprite.png");}', id: 'sprite-css', status: 'reference');
        $this->addAsset('references/icons/sprite.json', '{"icons": [{"name": "docs", "x": 0}]}', id: 'sprite-json', status: 'reference');
        self::assertSame(3, $this->runValidator(0)['checked']);
    }

    public function test_corrupt_json_fails_even_when_its_digest_matches(): void
    {
        $this->addAsset('references/icons/sprite.json', '{"icons": [}', status: 'reference');
        $this->assertError($this->runValidator(1), 'JSON sidecar must contain valid UTF-8 JSON');
    }

    public function test_duplicate_manifest_keys_are_rejected_at_every_depth(): void
    {
        $this->addAsset();
        $original = json_encode(['schema_version' => 1, 'assets' => $this->assets], JSON_THROW_ON_ERROR);
        foreach ([['schema_version', '0'], ['path', '"assets/other.png"'], ['status', '"legacy"'], ['sha256', '"wrong"'], ['source_sha256', '"wrong"']] as [$key, $first]) {
            $needle = '"' . $key . '":';
            $position = strpos($original, $needle);
            $raw = substr_replace($original, $needle . $first . ',' . $needle, $position, strlen($needle));
            $result = $this->runValidator(1, $raw);
            $this->assertError($result, 'duplicate JSON key');
            self::assertSame(0, $result['checked']);
        }
    }

    public function test_duplicate_sidecar_keys_are_rejected_even_with_a_matching_digest(): void
    {
        foreach (['{"icons": [], "icons": ["docs"]}', '{"icons": [{"name": "old", "name": "docs"}]}', '{"path": "old", "p\\u0061th": "new"}'] as $content) {
            $this->assets = [];
            $this->addAsset('references/icons/sprite.json', $content, status: 'reference');
            $this->assertError($this->runValidator(1), 'duplicate JSON key');
        }
    }

    public function test_equal_keys_in_separate_json_objects_are_valid(): void
    {
        $this->addAsset('references/icons/sprite.json', '{"icons": [{"name": "docs"}, {"name": "terminal"}]}', status: 'reference');
        self::assertSame(1, $this->runValidator(0)['checked']);
    }

    public function test_json_sidecars_accept_lists_and_reject_scalar_values(): void
    {
        $this->addAsset('references/icons/sprite.json', '["docs", "terminal"]', status: 'reference');
        $this->runValidator(0);
        $this->assets = [];
        $this->addAsset('references/icons/sprite.json', '"not an inventory"', status: 'reference');
        $this->assertError($this->runValidator(1), 'JSON sidecar must contain an object or list');
    }

    public function test_css_sidecars_reject_external_urls_and_traversal(): void
    {
        foreach ([
            '.icon {background: url("https://example.invalid/sprite.png")}',
            '.icon {background: url("../../../../sprite.png")}',
            '.icon {background: url("%2e%2e/%2e%2e/%2e%2e/sprite.png")}',
            '@import "https://example.invalid/style.css";',
        ] as $css) {
            $this->assets = [];
            $this->addAsset('references/icons/sprite.css', $css, status: 'reference');
            self::assertNotEmpty($this->runValidator(1)['errors']);
        }
    }

    public function test_css_sidecar_dependency_must_exist(): void
    {
        $this->addAsset('references/icons/sprite.css', '.icon {background: url("./missing.png")}', status: 'reference');
        $this->assertError($this->runValidator(1), 'CSS sidecar URL points to a missing file');
    }

    public function test_css_sidecars_reject_image_set_string_sources(): void
    {
        foreach ([
            '.icon {background-image: image-set("https://example.invalid/a.png" 1x)}',
            '.icon {background-image: -webkit-image-set("https://example.invalid/a.png" 1x)}',
            '.icon {background-image: IMAGE-SET("./sprite.png" 1x)}',
            '.icon {background-image: image/**/-set("https://example.invalid/a.png" 1x)}',
        ] as $css) {
            $this->assets = [];
            $this->addAsset('references/icons/sprite.css', $css, status: 'reference');
            $this->assertError($this->runValidator(1), 'CSS sidecar image-set is not permitted');
        }
    }

    public function test_svg_css_rejects_image_set_string_sources(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><style>.x {fill: image-set("https://example.invalid/a.png" 1x)}</style></svg>';
        $this->addAsset('assets/logo.svg', $svg);
        $this->assertError($this->runValidator(1), 'SVG CSS image-set is not permitted');
    }

    public function test_unmanaged_token_exports_do_not_need_inventory_entries(): void
    {
        $this->addAsset();
        $this->write('assets/tokens.json', '{"color": "#000000"}');
        $this->write('assets/theme.css', ':root {--color: #000000;}');
        $this->runValidator(0);
    }
}
