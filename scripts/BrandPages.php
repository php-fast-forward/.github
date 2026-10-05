<?php

declare(strict_types=1);

namespace PhpFastForward\Brand;

use Dom\HTMLDocument;
use Dom\Element;
use RuntimeException;
use stdClass;

/** Stage an explicit public inventory without exporting the checkout. */
final class BrandPages
{
    private const ACTIVE = ['canonical', 'package-variant', 'exploratory', 'reference'];
    private const FORBIDDEN = ['backup', 'backups', 'private', '.git', '.agents', '.codex'];
    private const GUIDES = ['README.md', 'DESIGN.md', 'STYLE.md', 'SOUL.md', 'CONTRIBUTING.md', 'SUPPORT.md', 'SECURITY.md', 'CODE_OF_CONDUCT.md', 'ACCESSIBILITY.md'];
    private const PROFILE = ['profile/assets/brand-hero-banner.png', 'profile/assets/docs-installation.png'];

    public static function safeFile(string $root, string $relative): string
    {
        $root = realpath($root) ?: throw new RuntimeException('Missing public root');
        $parts = explode('/', $relative);
        if ($relative === '' || str_contains($relative, '\\') || str_starts_with($relative, '/') || str_contains($parts[0], ':') || array_intersect($parts, ['', '.', '..'])) {
            throw new RuntimeException("Unsafe path: {$relative}");
        }
        if (array_intersect($parts, self::FORBIDDEN)) {
            throw new RuntimeException("Private/archive files are not publishable: {$relative}");
        }
        $current = $root;
        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                throw new RuntimeException("Hidden source files are not publishable: {$relative}");
            }
            $current .= '/' . $part;
            if (is_link($current)) {
                throw new RuntimeException("Symlinks are not publishable: {$relative}");
            }
        }
        $resolved = realpath($current);
        if (!is_file($current) || $resolved === false || !str_starts_with($resolved, $root . '/')) {
            throw new RuntimeException("Missing public file: {$relative}");
        }

        return $current;
    }

    public static function readPublicJson(string $root, string $relative): stdClass|array
    {
        try {
            $document = BrandValidator::decodeJson(file_get_contents(self::safeFile($root, $relative)), true);
        } catch (\Throwable $error) {
            throw new RuntimeException("Invalid public JSON in {$relative}: {$error->getMessage()}", previous: $error);
        }
        if (!$document instanceof stdClass && !is_array($document)) {
            throw new RuntimeException("Public JSON must contain an object or list: {$relative}");
        }

        return $document;
    }

    public static function publicationAllowed(stdClass $row): bool
    {
        if (!property_exists($row, 'publication')) {
            return true;
        }
        $record = $row->publication;
        if (!$record instanceof stdClass || ($record->authorization ?? null) !== 'maintainer-request') {
            return false;
        }
        foreach (['recorded_at', 'evidence'] as $field) {
            if (!is_string($record->$field ?? null) || trim($record->$field) === '') {
                return false;
            }
        }
        if (($record->scope ?? null) === 'brand-public-library') {
            return ($record->status ?? null) === 'authorized';
        }

        return ($record->scope ?? null) === 'brand-review-gallery'
            && ($row->status ?? null) === 'exploratory'
            && ($row->review_status ?? null) === 'awaiting-review'
            && ($record->canonical_selection ?? null) === false
            && ($record->status ?? 'authorized') === 'authorized';
    }

    /** @return array{array<string,true>, stdClass} */
    private static function inventory(string $root, string $repository, string $ref): array
    {
        $manifest = self::readPublicJson($root, 'assets/manifest.json');
        if (!$manifest instanceof stdClass || !is_array($manifest->assets ?? null)) {
            throw new RuntimeException('Invalid asset inventory');
        }
        $selected = [];
        $rows = [];
        foreach ($manifest->assets as $row) {
            if (!in_array($row->status, self::ACTIVE, true) || !self::publicationAllowed($row)) {
                continue;
            }
            if (($row->status === 'exploratory' && ($row->review_status ?? null) === 'awaiting-review')
                && ($row->publication->scope ?? null) !== 'brand-review-gallery') {
                continue;
            }
            $relative = $row->path;
            $ecosystem = str_starts_with($relative, 'references/ecosystem/')
                && str_ends_with($relative, '.png')
                && $row->status === 'reference'
                && ($row->publication->scope ?? null) === 'brand-public-library'
                && ($row->publication->status ?? null) === 'authorized';
            if (!str_starts_with($relative, 'assets/') && !in_array($relative, self::PROFILE, true) && !$ecosystem) {
                continue;
            }
            if (hash_file('sha256', self::safeFile($root, $relative)) !== $row->sha256) {
                throw new RuntimeException("Asset changed after cataloging: {$relative}");
            }
            $selected[$relative] = true;
            $rows[] = $row;
        }
        foreach (self::GUIDES as $relative) {
            if (file_exists($root . '/' . $relative)) {
                $selected[$relative] = true;
            }
        }
        $selected['assets/README.md'] = $selected['assets/manifest.json'] = true;
        foreach (new \DirectoryIterator($root . '/docs/brand') as $file) {
            if (in_array($file->getExtension(), ['html', 'md', 'json'], true)) {
                $selected['docs/brand/' . $file->getFilename()] = true;
            }
        }
        foreach (['tokens.json', 'exports.json', 'tailwind.theme.json', 'theme.css'] as $name) {
            if (file_exists($root . '/assets/tokens/' . $name)) {
                $selected['assets/tokens/' . $name] = true;
            }
        }
        if (is_dir($root . '/assets/brand/source')) {
            foreach (self::files($root . '/assets/brand/source') as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['ttf', 'txt', 'json', 'md'], true) || in_array(basename($file), ['LICENSE', 'OFL', 'COPYING'], true)) {
                    $selected[substr($file, strlen($root) + 1)] = true;
                }
            }
        }
        // Include the complete standalone logo generator, not an unusable entrypoint.
        foreach (['scripts/build-brand-logos.php', 'scripts/BrandLogos.php'] as $relative) {
            if (file_exists($root . '/' . $relative)) {
                $selected[$relative] = true;
            }
        }
        foreach (['README.md', 'README.pt-BR.md', 'README.es.md'] as $name) {
            if (file_exists($root . '/profile/' . $name)) {
                $selected['profile/' . $name] = true;
            }
        }
        foreach ($selected as $relative => $_) {
            self::safeFile($root, $relative);
            if (str_ends_with($relative, '.json')) {
                self::readPublicJson($root, $relative);
            }
        }
        $manifest->assets = $rows;
        $registry = array_merge($rows, $manifest->archived_sources ?? []);
        $ids = array_column($registry, 'id');
        foreach ($registry as $row) {
            $provenance = $row->provenance ?? null;
            if (!$provenance instanceof stdClass) {
                continue;
            }
            $sourceIds = $provenance->source_ids ?? [];
            $external = array_values(array_diff($sourceIds, $ids));
            if ($external) {
                $provenance->source_ids = array_values(array_intersect($sourceIds, $ids));
                $provenance->repository_source_ids = array_merge($provenance->repository_source_ids ?? [], $external);
            }
            if ($provenance->repository_source_ids ?? []) {
                $provenance->repository_manifest = rtrim($repository, '/') . '/blob/' . rawurlencode($ref) . '/assets/manifest.json';
            }
        }
        $manifest->description .= ' Pages export: active public assets only; references remain in the repository.';

        return [$selected, $manifest];
    }

    private static function files(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symlinks are not publishable');
            }
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function url(string $destination): array
    {
        $destination = html_entity_decode($destination, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/[\x00-\x20\x7f]/', $destination)) {
            throw new RuntimeException('Whitespace/control characters are not supported in URLs');
        }
        $parsed = parse_url($destination);
        if ($parsed === false) {
            throw new RuntimeException("Invalid URL: {$destination}");
        }

        return $parsed;
    }

    public static function localTarget(string $owner, string $destination): ?array
    {
        $parsed = self::url($destination);
        if (isset($parsed['scheme']) || isset($parsed['host']) || ($parsed['path'] ?? '') === '') {
            return null;
        }
        $path = rawurldecode($parsed['path']);
        if (str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new RuntimeException("Use repository-relative URLs in {$owner}: {$destination}");
        }
        $parts = [];
        foreach (explode('/', dirname($owner) . '/' . $path) as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (!$parts) {
                    throw new RuntimeException("URL escapes the repository in {$owner}: {$destination}");
                }
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return [implode('/', $parts), $parsed];
    }

    private static function resourceTarget(string $owner, string $destination): array
    {
        $parsed = self::url($destination);
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            throw new RuntimeException("External resource in {$owner}: {$destination}");
        }
        $target = self::localTarget($owner, $destination);
        if ($target === null && isset($parsed['fragment']) && $parsed['fragment'] !== '') {
            return [$owner, $parsed];
        }
        if ($target === null) {
            throw new RuntimeException("Empty resource in {$owner}: {$destination}");
        }

        return $target;
    }

    private static function sourceUrl(string $root, string $relative, array $parsed, string $repository, string $ref): string
    {
        $parts = explode('/', $relative);
        if (array_intersect($parts, self::FORBIDDEN)) {
            throw new RuntimeException("Private/archive links are not publishable: {$relative}");
        }
        $source = $root;
        foreach ($parts as $part) {
            $source .= '/' . $part;
            if (is_link($source)) {
                throw new RuntimeException("Symlinks are not publishable: {$relative}");
            }
        }
        if (!file_exists($source)) {
            throw new RuntimeException("Missing repository source link: {$relative}");
        }
        $url = rtrim($repository, '/') . '/' . (is_dir($source) ? 'tree' : 'blob') . '/' . rawurlencode($ref) . '/' . implode('/', array_map('rawurlencode', $parts));

        return $url . (isset($parsed['query']) ? '?' . $parsed['query'] : '') . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
    }

    public static function cssResources(string $owner, string $css): array
    {
        if (str_contains($css, '\\')) {
            throw new RuntimeException("CSS escapes are not supported in {$owner}");
        }
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        if (preg_match('/@\s*(?:import|font-face)\b|(?:-webkit-)?image-set\s*\(/i', $css)) {
            throw new RuntimeException("CSS imports, font-face and image-set are not supported in {$owner}");
        }
        preg_match_all('/url\s*\((.*?)\)/is', $css, $matches);

        return array_map(fn ($value) => trim(trim(trim($value), '\"\'')), $matches[1]);
    }

    private static function resourceAttribute(string $tag, string $name): bool
    {
        return in_array($name, ['src', 'srcset', 'poster', 'background'], true)
            || ($name === 'data' && $tag === 'object')
            || (in_array($name, ['href', 'xlink:href'], true) && !in_array($tag, ['a', 'area'], true));
    }

    private static function document(string $text): HTMLDocument
    {
        return HTMLDocument::createFromString($text, LIBXML_NOERROR, 'UTF-8');
    }

    /** @return array{list<array{string,bool}>, list<string>} */
    private static function inspect(HTMLDocument $document, string $owner, ?\Closure $rewrite = null): array
    {
        $links = [];
        $ids = [];
        foreach ($document->querySelectorAll('*') as $element) {
            $tag = strtolower($element->localName);
            if ($tag === 'base' || $element->hasAttribute('srcdoc') || $element->hasAttribute('xml:base')) {
                throw new RuntimeException("Embedded documents and alternate bases are not supported in {$owner}");
            }
            if ($element->hasAttribute('id')) {
                $ids[] = $element->getAttribute('id');
            }
            if ($rewrite && $tag === 'img') {
                $destination = $element->getAttribute('src');
                $parsed = self::url($destination);
                $target = self::localTarget($owner, $destination);
                $label = $element->getAttribute('alt') ?: 'Reference artwork';
                if (str_ends_with($owner, '.md') && (isset($parsed['scheme']) || isset($parsed['host']))) {
                    $element->replaceWith($document->createTextNode($label));
                    continue;
                }
                if ($target && str_starts_with($target[0], 'references/')) {
                    $referenceUrl = $rewrite($destination, false);
                    if ($referenceUrl !== $destination) {
                        $anchor = $document->createElement('a');
                        $anchor->setAttribute('href', $referenceUrl);
                        $anchor->textContent = 'View reference in the repository: ' . $label;
                        $element->replaceWith($anchor);
                        continue;
                    }
                }
            }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                if ($name === 'srcset') {
                    $entries = [];
                    foreach (explode(',', $value) as $entry) {
                        $parts = preg_split('/\s+/', trim($entry));
                        if ($parts[0] === '') {
                            continue;
                        }
                        $links[] = [$parts[0], true];
                        if ($rewrite) {
                            $parts[0] = $rewrite($parts[0], true);
                        }
                        $entries[] = implode(' ', $parts);
                    }
                    if (!$entries) {
                        throw new RuntimeException("Empty resource in {$owner}: srcset");
                    }
                    if ($rewrite) {
                        $element->setAttribute($attribute->name, implode(', ', $entries));
                    }
                } elseif (in_array($name, ['href', 'xlink:href', 'src', 'poster', 'background'], true) || ($name === 'data' && $tag === 'object')) {
                    $resource = self::resourceAttribute($tag, $name);
                    $links[] = [$value, $resource];
                    if ($rewrite) {
                        $element->setAttribute($attribute->name, $rewrite($value, $resource));
                    }
                } elseif ($name === 'style' || (in_array($name, ['fill', 'stroke', 'filter', 'mask', 'clip-path', 'cursor'], true) && (str_contains($value, '\\') || preg_match('/url\s*\(/i', $value)))) {
                    foreach (self::cssResources($owner, $value) as $reference) {
                        $links[] = [$reference, true];
                        if ($rewrite) {
                            $rewrite($reference, true);
                        }
                    }
                }
            }
            if ($tag === 'style') {
                foreach (self::cssResources($owner, $element->textContent) as $reference) {
                    $links[] = [$reference, true];
                    if ($rewrite) {
                        $rewrite($reference, true);
                    }
                }
            }
        }

        return [$links, $ids];
    }

    private static function rewriteDocument(string $root, string $owner, string $text, array $selected, string $repository, string $ref): string
    {
        $link = static function (string $destination, bool $resource = false) use ($root, $owner, $selected, $repository, $ref): string {
            $target = $resource ? self::resourceTarget($owner, $destination) : self::localTarget($owner, $destination);
            if ($target === null || isset($selected[$target[0]])) {
                return $destination;
            }
            if ($resource) {
                throw new RuntimeException("Unpublished resource in {$owner}: {$destination}");
            }

            return self::sourceUrl($root, $target[0], $target[1], $repository, $ref);
        };
        if (str_ends_with($owner, '.html')) {
            $document = self::document($text);
            self::inspect($document, $owner, $link);

            return $document->saveHtml();
        }
        // Markdown is a downloadable source. Protect fences and rewrite only
        // its explicit HTML fragments, retaining the surrounding source bytes.
        $parts = preg_split('/(```[\s\S]*?```|~~~[\s\S]*?~~~)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($index = 0; $index < count($parts); $index += 2) {
            // Validate all HTML tags in their actual nesting/namespace, including
            // audio/embed/table/SVG. Serialization of isolated start tags would
            // corrupt Markdown's original HTML and miss unsupported elements.
            self::inspect(self::document($parts[$index]), $owner, $link);
            $parts[$index] = self::rewriteMarkdownHtml($parts[$index], $owner, $link);
            $parts[$index] = preg_replace_callback('/(!?)\[([^\]]*)\]\(([^\s)]+)([^)]*)\)/', static function ($match) use ($link, $owner): string {
                [, $image, $label, $destination, $suffix] = $match;
                $parsed = self::url($destination);
                $target = self::localTarget($owner, $destination);
                if ($image && (isset($parsed['scheme']) || isset($parsed['host']))) {
                    return '[View external image: ' . $label . '](' . $link($destination) . $suffix . ')';
                }
                if ($image && $target && str_starts_with($target[0], 'references/') && $link($destination) !== $destination) {
                    return '[View reference in the repository: ' . $label . '](' . $link($destination) . $suffix . ')';
                }

                return $image . '[' . $label . '](' . $link($destination, (bool) $image) . $suffix . ')';
            }, $parts[$index]);
        }

        return implode('', $parts);
    }

    private static function rewriteMarkdownHtml(string $text, string $owner, \Closure $link): string
    {
        // Retain comments and raw-text elements as complete tokens. A colon
        // followed by / cannot form a start tag, so Markdown autolinks survive.
        $tokens = '~<!--.*?-->|<(script|style)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?</\1\s*>|<[A-Za-z][A-Za-z0-9:-]*(?=\s|/?>)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is';

        return preg_replace_callback($tokens, static function ($match) use ($owner, $link): string {
            $token = $match[0];
            if (str_starts_with($token, '<!--') || preg_match('~^<(?:script|style)\b~i', $token)) {
                return $token;
            }
            preg_match('~^<([A-Za-z][A-Za-z0-9:-]*)~', $token, $tagMatch);
            $tag = strtolower($tagMatch[1]);
            $start = strlen($tagMatch[0]);
            $attributes = substr($token, $start);
            preg_match_all('~([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~', $attributes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
            $values = [];
            $replacements = [];
            foreach ($matches as $attribute) {
                $name = strtolower($attribute[1][0]);
                $value = null;
                foreach ([2, 3, 4] as $group) {
                    if ($attribute[$group][1] >= 0) {
                        $value = html_entity_decode($attribute[$group][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        break;
                    }
                }
                $values[$name] ??= $value;
                if ($value === null) {
                    continue; // DOM validation already rejects empty resources.
                }
                $replacement = $value;
                if ($name === 'srcset') {
                    $entries = [];
                    foreach (explode(',', $value) as $entry) {
                        $parts = preg_split('/\s+/', trim($entry));
                        if ($parts[0] !== '') {
                            $parts[0] = $link($parts[0], true);
                            $entries[] = implode(' ', $parts);
                        }
                    }
                    $replacement = implode(', ', $entries);
                } elseif (in_array($name, ['href', 'xlink:href', 'src', 'poster', 'background'], true) || ($name === 'data' && $tag === 'object')) {
                    // Remote Markdown badges were validated as source-only
                    // labels above; they are replaced below before publishing.
                    if (!($tag === 'img' && $name === 'src')) {
                        $replacement = $link($value, self::resourceAttribute($tag, $name));
                    }
                }
                if ($replacement !== $value) {
                    $replacements[] = [$start + $attribute[0][1], strlen($attribute[0][0]), $attribute[1][0] . '="' . htmlspecialchars($replacement, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"'];
                }
            }
            if ($tag === 'img') {
                $destination = $values['src'] ?? '';
                $parsed = self::url($destination);
                $label = $values['alt'] ?? 'Reference artwork';
                if (isset($parsed['scheme']) || isset($parsed['host'])) {
                    return htmlspecialchars($label, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
                }
                $target = self::localTarget($owner, $destination);
                if ($target && str_starts_with($target[0], 'references/') && $link($destination) !== $destination) {
                    return '<a href="' . htmlspecialchars($link($destination), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">View reference in the repository: ' . htmlspecialchars($label, ENT_NOQUOTES | ENT_HTML5, 'UTF-8') . '</a>';
                }
            }
            foreach (array_reverse($replacements) as [$offset, $length, $value]) {
                $token = substr_replace($token, $value, $offset, $length);
            }

            return $token;
        }, $text);
    }

    public static function checkOutput(string $output): void
    {
        $documents = [];
        foreach (self::files($output) as $file) {
            $relative = substr($file, strlen($output) + 1);
            if (str_ends_with($relative, '.html')) {
                $documents[$relative] = self::inspect(self::document(file_get_contents($file)), $relative);
            }
        }
        foreach ($documents as $owner => [$links]) {
            foreach ($links as [$destination, $resource]) {
                $parsed = self::url($destination);
                $target = $resource ? self::resourceTarget($owner, $destination) : self::localTarget($owner, $destination);
                if (isset($parsed['scheme']) || isset($parsed['host'])) {
                    continue;
                }
                $relative = $target[0] ?? $owner;
                self::safeFile($output, $relative);
                if (isset($parsed['fragment'], $documents[$relative]) && !in_array(rawurldecode($parsed['fragment']), $documents[$relative][1], true)) {
                    throw new RuntimeException("Missing anchor in {$owner}: {$destination}");
                }
            }
        }
        foreach (self::files($output) as $file) {
            if (str_ends_with($file, '.css')) {
                $owner = substr($file, strlen($output) + 1);
                foreach (self::cssResources($owner, file_get_contents($file)) as $destination) {
                    self::safeFile($output, self::resourceTarget($owner, $destination)[0]);
                }
            }
        }
    }

    public static function build(string $root, string $output, string $repository, string $ref): int
    {
        $root = realpath($root) ?: throw new RuntimeException('Missing repository root');
        if (!str_starts_with($repository, 'https://github.com/')) {
            throw new RuntimeException('Repository URL must name the public GitHub repository');
        }
        $output = self::absolutePath($output);
        if (is_link($output) || $output === $root || str_starts_with($root . '/', $output . '/')) {
            throw new RuntimeException('The destination must be a separate empty directory');
        }
        if (str_starts_with($output, $root . '/') && $output !== $root . '/_site') {
            throw new RuntimeException('Inside the checkout, only the _site destination is allowed');
        }
        if (file_exists($output) && (!is_dir($output) || count(scandir($output)) > 2)) {
            throw new RuntimeException('Refusing to overwrite a nonempty destination; choose a new directory');
        }
        [$selected, $manifest] = self::inventory($root, $repository, $ref);
        $documents = [];
        foreach ($selected as $relative => $_) {
            $file = self::safeFile($root, $relative);
            $extension = pathinfo($relative, PATHINFO_EXTENSION);
            if (in_array($extension, ['html', 'md'], true)) {
                $documents[$relative] = self::rewriteDocument($root, $relative, file_get_contents($file), $selected, $repository, $ref);
            } elseif ($extension === 'css') {
                foreach (self::cssResources($relative, file_get_contents($file)) as $destination) {
                    if (!isset($selected[self::resourceTarget($relative, $destination)[0]])) {
                        throw new RuntimeException("Unpublished resource in {$relative}: {$destination}");
                    }
                }
            }
        }
        // Validate the complete in-memory HTML graph before creating output.
        $html = [];
        foreach ($documents as $relative => $text) {
            if (str_ends_with($relative, '.html')) {
                $html[$relative] = self::inspect(self::document($text), $relative);
            }
        }
        foreach ($html as $owner => [$links]) {
            foreach ($links as [$destination, $resource]) {
                $parsed = self::url($destination);
                $target = $resource ? self::resourceTarget($owner, $destination) : self::localTarget($owner, $destination);
                if (isset($parsed['scheme']) || isset($parsed['host'])) {
                    continue;
                }
                $relative = $target[0] ?? $owner;
                if (!isset($selected[$relative])) {
                    throw new RuntimeException("Missing public file: {$relative}");
                }
                if (isset($parsed['fragment'], $html[$relative]) && !in_array(rawurldecode($parsed['fragment']), $html[$relative][1], true)) {
                    throw new RuntimeException("Missing anchor in {$owner}: {$destination}");
                }
            }
        }
        if (!is_dir($output)) {
            mkdir($output, 0777, true);
        }
        ksort($selected);
        foreach ($selected as $relative => $_) {
            $destination = $output . '/' . $relative;
            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0777, true);
            }
            if (isset($documents[$relative])) {
                file_put_contents($destination, $documents[$relative]);
            } else {
                copy(self::safeFile($root, $relative), $destination);
            }
        }
        file_put_contents($output . '/assets/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        file_put_contents($output . '/index.html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PHP Fast Forward brand library</title><meta http-equiv="refresh" content="0; url=docs/brand/index.html"></head><body><p><a href="docs/brand/index.html">Open the PHP Fast Forward brand library</a></p></body></html>' . "\n");
        touch($output . '/.nojekyll');
        self::checkOutput($output);

        return count($selected) + 2;
    }

    private static function absolutePath(string $path): string
    {
        $path = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
            $current = '/' . implode('/', $parts);
            if (is_link($current) && !in_array($current, ['/tmp', '/var'], true)) {
                throw new RuntimeException('Symlinks are not publishable destinations');
            }
        }
        $path = '/' . implode('/', $parts);
        // Resolve aliases such as macOS /tmp before containment checks.
        $tail = [];
        $existing = $path;
        while (!file_exists($existing) && $existing !== '/') {
            array_unshift($tail, basename($existing));
            $existing = dirname($existing);
        }

        return rtrim(realpath($existing), '/') . ($tail ? '/' . implode('/', $tail) : '');
    }
}
