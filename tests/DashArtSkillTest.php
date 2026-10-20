<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DashArtSkillTest extends TestCase
{
    private string $root;
    private const PACKAGE = __DIR__ . '/../resources/skills/fast-forward-dash-art';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dash-art-example-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/assets/mascot', 0777, true);
        file_put_contents($this->root . '/DESIGN.md', "# Test brand kit\n");
        file_put_contents($this->root . '/composer.json', '{"name":"php-fast-forward/brand-kit"}');
        $assets = [];
        foreach (['dash-developer-welcome', 'dash-developer-build', 'dash-editorial-hoodie'] as $id) {
            $path = 'assets/mascot/' . $id . '.png';
            copy(__DIR__ . '/../' . $path, $this->root . '/' . $path);
            $assets[] = ['id' => $id, 'family' => str_contains($id, 'developer') ? 'developer' : 'editorial',
                'path' => $path, 'status' => str_contains($id, 'developer') ? 'canonical' : 'package-variant',
                'sha256' => hash_file('sha256', $this->root . '/' . $path)];
        }
        file_put_contents($this->root . '/assets/manifest.json', json_encode(['assets' => $assets], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function brief(): array
    {
        return json_decode(file_get_contents(self::PACKAGE . '/examples/repository-header.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function runExample(array|string $brief): array
    {
        $path = $this->root . '/brief.json';
        file_put_contents($path, is_array($brief) ? json_encode($brief, JSON_THROW_ON_ERROR) : $brief);
        $process = proc_open([PHP_BINARY, self::PACKAGE . '/examples/resolve-references.php', $this->root, $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public function test_bundled_example_resolves_verified_files_without_mutating_the_kit(): void
    {
        $before = hash_file('sha256', $this->root . '/assets/manifest.json');
        [$exit, $stdout, $stderr] = $this->runExample($this->brief());
        self::assertSame(0, $exit, $stderr);
        $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ready-not-generated', $result['status']);
        self::assertSame(['dash-developer-welcome', 'dash-developer-build'], array_column($result['references'], 'id'));
        foreach ($result['references'] as $reference) {
            self::assertSame(hash_file('sha256', $this->root . '/' . $reference['path']), $reference['sha256']);
        }
        self::assertSame($before, hash_file('sha256', $this->root . '/assets/manifest.json'));
        self::assertSame(3, count(glob($this->root . '/assets/mascot/*')));
    }

    public function test_inline_first_use_json_is_the_executed_complete_example(): void
    {
        preg_match('/\\x60{3}json\\s*(.*?)\\x60{3}/s', file_get_contents(self::PACKAGE . '/SKILL.md'), $match);
        $brief = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($this->brief(), $brief);
        [$exit, , $stderr] = $this->runExample($brief);
        self::assertSame(0, $exit, $stderr);
    }

    public function test_cross_family_source_is_rejected(): void
    {
        $brief = $this->brief();
        $brief['preferred_pose_id'] = 'dash-editorial-hoodie';
        [$exit, , $stderr] = $this->runExample($brief);
        self::assertSame(1, $exit);
        self::assertStringContainsString('requested family', $stderr);
    }

    public function test_missing_brief_data_is_rejected(): void
    {
        $brief = $this->brief();
        unset($brief['purpose']);
        [$exit, , $stderr] = $this->runExample($brief);
        self::assertSame(1, $exit);
        self::assertStringContainsString('purpose', $stderr);
    }

    public function test_changed_reference_bytes_are_rejected(): void
    {
        file_put_contents($this->root . '/assets/mascot/dash-developer-welcome.png', 'changed');
        [$exit, , $stderr] = $this->runExample($this->brief());
        self::assertSame(1, $exit);
        self::assertStringContainsString('changed reference bytes', $stderr);
    }

    public function test_missing_reference_file_is_rejected(): void
    {
        unlink($this->root . '/assets/mascot/dash-developer-welcome.png');
        [$exit, , $stderr] = $this->runExample($this->brief());
        self::assertSame(1, $exit);
        self::assertStringContainsString('Missing or changed reference bytes', $stderr);
    }

    public function test_invalid_json_fails_instead_of_claiming_a_ready_brief(): void
    {
        [$exit, $stdout, $stderr] = $this->runExample('{');
        self::assertSame(1, $exit);
        self::assertSame('', $stdout);
        self::assertNotEmpty($stderr);
    }
}

