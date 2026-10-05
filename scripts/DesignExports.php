<?php

declare(strict_types=1);

namespace PhpFastForward\Brand;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/** Export the repository's DESIGN.md token schema without an external CLI. */
final class DesignExports
{
    private const SECTIONS = ['Overview', 'Colors', 'Typography', 'Layout', 'Elevation & Depth', 'Shapes', 'Components', "Do's and Don'ts"];
    private const TYPOGRAPHY = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing'];
    private const COMPONENTS = ['backgroundColor', 'textColor', 'typography', 'rounded', 'padding', 'size', 'height', 'width'];

    public static function fromMarkdown(string $markdown): array
    {
        if (!preg_match('/\A(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n---(?:\r?\n|\z)/s', $markdown, $match)) {
            throw new RuntimeException('DESIGN.md must start with YAML frontmatter');
        }
        $data = Yaml::parse($match[1]);
        self::mapping($data, 'frontmatter');
        self::keys($data, ['version', 'name', 'description', 'colors', 'typography', 'rounded', 'spacing', 'components'], 'frontmatter');
        if (($data['version'] ?? null) !== 'alpha' || !is_string($data['name'] ?? null) || trim($data['name']) === '') {
            throw new RuntimeException('DESIGN.md requires version alpha and a nonempty name');
        }
        if (isset($data['description']) && !is_string($data['description'])) {
            throw new RuntimeException('description must be a string');
        }
        $previous = -1;
        foreach (self::SECTIONS as $section) {
            if (!preg_match('/^## ' . preg_quote($section, '/') . '\s*$/m', $markdown, $heading, PREG_OFFSET_CAPTURE) || $heading[0][1] <= $previous) {
                throw new RuntimeException("Missing or out-of-order section: {$section}");
            }
            $previous = $heading[0][1];
        }
        foreach (['colors', 'typography', 'rounded', 'spacing', 'components'] as $group) {
            self::mapping($data[$group] ?? null, $group);
            foreach ($data[$group] as $name => $value) {
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $name)) {
                    throw new RuntimeException("Invalid token name: {$group}.{$name}");
                }
                $data[$group][$name] = self::resolve($data, $value);
            }
        }
        if (!isset($data['colors']['primary'])) {
            throw new RuntimeException('colors.primary is required');
        }
        foreach ($data['colors'] as $name => $value) {
            self::color($value, "colors.{$name}");
        }
        foreach (['rounded', 'spacing'] as $group) {
            foreach ($data[$group] as $name => $value) {
                $dimension = self::dimension($value, "{$group}.{$name}", false);
                if ($dimension['value'] < 0) {
                    throw new RuntimeException("Negative dimension: {$group}.{$name}");
                }
            }
        }
        foreach ($data['typography'] as $name => $value) {
            self::typography($value, "typography.{$name}");
        }
        foreach ($data['components'] as $name => $component) {
            self::mapping($component, "components.{$name}");
            self::keys($component, self::COMPONENTS, "components.{$name}");
            foreach ($component as $key => $value) {
                if (in_array($key, ['backgroundColor', 'textColor'], true)) {
                    self::color($value, "components.{$name}.{$key}");
                } elseif ($key === 'typography') {
                    self::typography($value, "components.{$name}.typography");
                } else {
                    $dimension = self::dimension($value, "components.{$name}.{$key}", true);
                    if ($dimension['value'] < 0) {
                        throw new RuntimeException("Negative component dimension: {$name}.{$key}");
                    }
                }
            }
            if (isset($component['backgroundColor'], $component['textColor'])) {
                $minimum = 4.5;
                if (isset($component['typography'])) {
                    $font = $component['typography'];
                    $size = self::dimension($font['fontSize'], 'fontSize', false);
                    $pixels = $size['value'] * ($size['unit'] === 'rem' ? 16 : 1);
                    if ($pixels >= 24 || ($pixels >= 56 / 3 && (float) $font['fontWeight'] >= 700)) {
                        $minimum = 3;
                    }
                }
                $ratio = self::contrast($component['backgroundColor'], $component['textColor']);
                if ($ratio < $minimum) {
                    throw new RuntimeException(sprintf('Text contrast for components.%s is %.2f:1; requires %.1f:1', $name, $ratio, $minimum));
                }
            }
        }

        return $data;
    }

    private static function mapping(mixed $value, string $label): void
    {
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            throw new RuntimeException("{$label} must be a nonempty token mapping");
        }
    }

    private static function keys(array $value, array $allowed, string $label): void
    {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new RuntimeException("Unknown property: {$label}.{$key}");
            }
        }
    }

    private static function resolve(array $data, mixed $value, array $trail = []): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::resolve($data, $item, $trail), $value);
        }
        if (!is_string($value) || (!str_contains($value, '{') && !str_contains($value, '}'))) {
            return $value;
        }
        if (!preg_match('/^\{([a-z0-9-]+(?:\.[a-z0-9-]+)+)\}$/', $value, $match)) {
            throw new RuntimeException("Invalid token reference: {$value}");
        }
        $reference = $match[1];
        if (in_array($reference, $trail, true) || count($trail) >= 10) {
            throw new RuntimeException("Cyclic or too-deep token reference: {$reference}");
        }
        $target = $data;
        foreach (explode('.', $reference) as $part) {
            if (!is_array($target) || !array_key_exists($part, $target)) {
                throw new RuntimeException("Broken token reference: {$reference}");
            }
            $target = $target[$part];
        }

        return self::resolve($data, $target, [...$trail, $reference]);
    }

    public static function fontFamilies(string $stack): array
    {
        $names = [];
        $name = $quote = '';
        $escaped = false;
        foreach (str_split($stack) as $character) {
            if ($escaped) {
                $name .= $character;
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($quote !== '') {
                if ($character === $quote) {
                    $quote = '';
                } else {
                    $name .= $character;
                }
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === ',') {
                $names[] = trim($name);
                $name = '';
            } else {
                $name .= $character;
            }
        }
        $names[] = trim($name);
        if ($quote !== '' || $escaped || in_array('', $names, true)) {
            throw new RuntimeException('Invalid font-family stack');
        }

        return $names;
    }

    private static function dimension(mixed $value, string $label, bool $allowEm): array
    {
        if (!is_string($value) || !preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(px|rem|em)$/', $value, $match) || (!$allowEm && $match[2] === 'em')) {
            throw new RuntimeException("Unsupported dimension: {$label}; use " . ($allowEm ? 'px, rem or em' : 'px or rem'));
        }

        if (!is_finite((float) $match[1])) {
            throw new RuntimeException("Nonfinite dimension: {$label}");
        }

        return ['value' => self::number((float) $match[1]), 'unit' => $match[2]];
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value && abs($value) <= PHP_INT_MAX ? (int) $value : $value;
    }

    private static function typography(mixed $value, string $label): void
    {
        self::mapping($value, $label);
        self::keys($value, self::TYPOGRAPHY, $label);
        foreach (self::TYPOGRAPHY as $key) {
            if (!array_key_exists($key, $value)) {
                throw new RuntimeException("Typography {$label} is missing {$key}");
            }
        }
        if (!is_string($value['fontFamily'])) {
            throw new RuntimeException("Invalid fontFamily: {$label}");
        }
        self::fontFamilies($value['fontFamily']);
        $size = self::dimension($value['fontSize'], "{$label}.fontSize", false);
        self::dimension($value['letterSpacing'], "{$label}.letterSpacing", true);
        if ($size['value'] <= 0 || !is_numeric($value['fontWeight']) || (float) $value['fontWeight'] < 1 || (float) $value['fontWeight'] > 1000) {
            throw new RuntimeException("Invalid font size or weight: {$label}");
        }
        if (!is_numeric($value['lineHeight']) || !is_finite((float) $value['lineHeight']) || (float) $value['lineHeight'] <= 0) {
            throw new RuntimeException("Invalid unitless lineHeight: {$label}");
        }
    }

    private static function color(mixed $value, string $label): array
    {
        if (!is_string($value) || !preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value)) {
            throw new RuntimeException("Unsupported color: {$label}; use opaque #RGB or #RRGGBB");
        }
        $hex = strtolower(substr($value, 1));
        if (strlen($hex) === 3) {
            $hex = implode('', array_map(fn ($digit) => $digit . $digit, str_split($hex)));
        }

        return ['hex' => '#' . $hex, 'channels' => array_map(fn ($pair) => hexdec($pair) / 255, str_split($hex, 2))];
    }

    private static function contrast(string $background, string $text): float
    {
        $luminance = static function (string $color): float {
            $channels = array_map(fn ($channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4, self::color($color, 'contrast')['channels']);

            return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        };
        $first = $luminance($background);
        $second = $luminance($text);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    public static function json(array $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return preg_replace_callback('/^( +)/m', fn ($match) => str_repeat(' ', intdiv(strlen($match[1]), 2)), $json) . "\n";
    }

    /** @return array<string, array{format: string, value: string}> */
    public static function generate(string $markdown): array
    {
        $data = self::fromMarkdown($markdown);
        $tokens = isset($data['description']) ? ['$description' => $data['description']] : [];
        $tokens['color'] = ['$type' => 'color'];
        $theme = ['colors' => [], 'fontFamily' => [], 'fontSize' => [], 'borderRadius' => [], 'spacing' => []];
        $css = ['color' => [], 'font' => [], 'text' => [], 'leading' => [], 'tracking' => [], 'font-weight' => [], 'radius' => [], 'spacing' => []];
        foreach ($data['colors'] as $name => $value) {
            $color = self::color($value, $name);
            $tokens['color'][$name] = ['$value' => ['colorSpace' => 'srgb', 'components' => array_map(fn ($channel) => self::number(round($channel, 3)), $color['channels']), 'hex' => $color['hex']]];
            $theme['colors'][$name] = $css['color'][$name] = $color['hex'];
        }
        foreach (['spacing' => 'spacing', 'rounded' => 'borderRadius'] as $group => $tailwind) {
            $tokens[$group] = ['$type' => 'dimension'];
            foreach ($data[$group] as $name => $value) {
                $tokens[$group][$name] = ['$value' => self::dimension($value, $name, false)];
                $theme[$tailwind][$name] = $value;
                $css[$group === 'rounded' ? 'radius' : 'spacing'][$name] = $value;
            }
        }
        $tokens['typography'] = [];
        foreach ($data['typography'] as $name => $value) {
            $families = self::fontFamilies($value['fontFamily']);
            $size = self::dimension($value['fontSize'], $name, false);
            $tracking = self::dimension($value['letterSpacing'], $name, true);
            if ($tracking['unit'] === 'em') {
                $tracking = ['value' => self::number((float) sprintf('%.12g', $tracking['value'] * $size['value'])), 'unit' => $size['unit']];
            }
            $weight = self::number((float) $value['fontWeight']);
            $leading = self::number((float) $value['lineHeight']);
            $tokens['typography'][$name] = ['$type' => 'typography', '$value' => ['fontFamily' => $families, 'fontSize' => $size, 'fontWeight' => $weight, 'letterSpacing' => $tracking, 'lineHeight' => $leading]];
            $theme['fontFamily'][$name] = $families;
            $theme['fontSize'][$name] = [$value['fontSize'], ['lineHeight' => (string) $leading, 'letterSpacing' => $value['letterSpacing'], 'fontWeight' => (string) $weight]];
            $css['font'][$name] = implode(', ', array_map(fn ($family) => preg_match('/[\s,]/', $family) ? json_encode($family, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $family, $families));
            $css['text'][$name] = $value['fontSize'];
            $css['leading'][$name] = (string) $leading;
            $css['tracking'][$name] = $value['letterSpacing'];
            $css['font-weight'][$name] = (string) $weight;
        }
        $lines = ['@theme {'];
        foreach ($css as $namespace => $values) {
            foreach ($values as $name => $value) {
                $lines[] = "  --{$namespace}-{$name}: {$value};";
            }
        }
        foreach (['leading' => 'line-height', 'tracking' => 'letter-spacing', 'font-weight' => 'font-weight'] as $namespace => $property) {
            foreach ($css[$namespace] as $name => $value) {
                $lines[] = "  --text-{$name}--{$property}: {$value};";
            }
        }
        $lines[] = '}';

        return [
            'tokens.json' => ['format' => 'dtcg', 'value' => self::json($tokens)],
            'tailwind.theme.json' => ['format' => 'json-tailwind', 'value' => self::json(['theme' => ['extend' => $theme]])],
            'theme.css' => ['format' => 'css-tailwind', 'value' => implode("\n", $lines) . "\n"],
        ];
    }

    public static function metadata(string $root, array $outputs): array
    {
        $rows = [];
        foreach ($outputs as $name => $output) {
            $rows[] = ['path' => $name, 'format' => $output['format'], 'sha256' => hash('sha256', $output['value'])];
        }

        return [
            'schema_version' => 1,
            'source' => 'DESIGN.md',
            'source_sha256' => hash_file('sha256', $root . '/DESIGN.md'),
            'generator' => 'php scripts/export-design.php',
            'normalization' => 'scripts/DesignExports.php',
            'normalization_sha256' => hash_file('sha256', __FILE__),
            'outputs' => $rows,
        ];
    }

    public static function verifyBrowserAdapter(string $theme, string $adapter): void
    {
        $expected = self::declarations($theme, false);
        $actual = self::declarations($adapter, true);
        foreach ($actual as $name => $value) {
            if (!array_key_exists($name, $expected)) {
                throw new RuntimeException("Browser token adapter has obsolete token {$name}");
            }
        }
        ksort($expected);
        ksort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException('Browser token adapter is stale; update active :root declarations from the generated theme');
        }
    }

    /** Read declarations in @theme or an unconditional :root, including CSS layers. */
    private static function declarations(string $css, bool $browser): array
    {
        $css = self::withoutComments($css);
        $declarations = $stack = [];
        $start = $parentheses = 0;
        $quote = '';
        $escaped = false;
        $consume = static function (string $segment) use (&$declarations, &$stack, $browser): void {
            $segment = trim($segment);
            if (!str_starts_with($segment, '--')) {
                return;
            }
            if (!preg_match('/^(--[\w-]+)\s*:\s*(.+)$/s', $segment, $match)) {
                throw new RuntimeException('Invalid or escaped token declaration');
            }
            [, $name, $value] = $match;
            if (!preg_match('/^--(?:color|font|text|leading|tracking|spacing|radius)-/', $name)) {
                return;
            }
            $scope = $stack;
            $selector = array_pop($scope);
            $active = $browser ? $selector === ':root' : $selector === '@theme';
            foreach ($scope as $parent) {
                $active = $active && $browser && (bool) preg_match('/^@layer(?:\s+[\w.-]+)?$/', $parent);
            }
            if (!$active) {
                throw new RuntimeException("Token {$name} must be in an active " . ($browser ? 'top-level :root' : '@theme') . ' declaration');
            }
            if (array_key_exists($name, $declarations)) {
                throw new RuntimeException("Browser token adapter duplicates {$name}");
            }
            $declarations[$name] = preg_match('/^--font-(?!weight-)/', $name)
                ? self::fontFamilies(trim($value)) : preg_replace('/\s+/', ' ', trim($value));
        };
        for ($index = 0, $length = strlen($css); $index < $length; ++$index) {
            $character = $css[$index];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === '\\') {
                $escaped = true;
                continue;
            }
            if ($quote !== '') {
                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '(') {
                ++$parentheses;
            } elseif ($character === ')') {
                --$parentheses;
            } elseif ($parentheses === 0 && $character === '{') {
                $stack[] = trim(substr($css, $start, $index - $start));
                $start = $index + 1;
            } elseif ($parentheses === 0 && ($character === ';' || $character === '}')) {
                $consume(substr($css, $start, $index - $start));
                if ($character === '}' && array_pop($stack) === null) {
                    throw new RuntimeException('Unbalanced CSS block');
                }
                $start = $index + 1;
            }
        }
        if ($stack !== [] || $quote !== '' || $parentheses !== 0 || $escaped) {
            throw new RuntimeException('Unbalanced CSS syntax');
        }

        return $declarations;
    }

    private static function withoutComments(string $css): string
    {
        $result = $quote = '';
        $escaped = false;
        for ($index = 0, $length = strlen($css); $index < $length; ++$index) {
            $character = $css[$index];
            if ($escaped) {
                $result .= $character;
                $escaped = false;
            } elseif ($character === '\\') {
                $result .= $character;
                $escaped = true;
            } elseif ($quote !== '') {
                $result .= $character;
                if ($character === $quote) {
                    $quote = '';
                }
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
                $result .= $character;
            } elseif ($character === '/' && ($css[$index + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $index + 2);
                if ($end === false) {
                    throw new RuntimeException('Unterminated CSS comment');
                }
                $result .= ' ';
                $index = $end + 1;
            } else {
                $result .= $character;
            }
        }

        return $result;
    }
}
