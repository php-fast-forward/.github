<?php

declare(strict_types=1);

namespace PhpFastForward\Brand;

use DOMDocument;
use DOMElement;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

/** Public asset inventory validation shared by the PHP delivery tools. */
final class BrandValidator
{
    private const STATUSES = ['canonical', 'reference', 'legacy', 'package-variant', 'exploratory'];
    private const IMAGE_FORMATS = [
        'png' => 'png', 'jpg' => 'jpeg', 'jpeg' => 'jpeg', 'svg' => 'svg',
        'webp' => 'webp', 'gif' => 'gif', 'avif' => 'avif', 'ico' => 'ico',
        'bmp' => 'bmp', 'tif' => 'tiff', 'tiff' => 'tiff',
    ];
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";
    private const MAX_PNG_DECOMPRESSED_BYTES = 128 * 1024 * 1024;
    private const LEGACY_SVG_DOCTYPE = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">';

    public static function relativePathError(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || str_contains($value, '\\')) {
            return 'path must be a nonempty repository-relative POSIX path';
        }
        if (preg_match('/[\x00-\x1f]/', $value)) {
            return 'path contains a control character';
        }
        $parts = explode('/', $value);
        if (str_starts_with($value, '/') || array_intersect($parts, ['', '.', '..'])) {
            return 'path must not be absolute or contain empty, . or .. components';
        }
        if (str_contains($parts[0], ':')) {
            return 'path must not use an absolute drive or URL prefix';
        }
        return null;
    }

    /** @return array{?string, ?string} */
    public static function safePath(string $root, mixed $relative): array
    {
        if (($error = self::relativePathError($relative)) !== null) {
            return [null, $error];
        }
        $current = rtrim($root, '/');
        foreach (explode('/', $relative) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) {
                return [null, 'path must not traverse a symlink'];
            }
        }
        return [$current, null];
    }

    private static function utf8(string $data, string $message): string
    {
        if (preg_match('//u', $data) !== 1) {
            throw new UnexpectedValueException($message);
        }
        return str_starts_with($data, "\xef\xbb\xbf") ? substr($data, 3) : $data;
    }

    /** Strict JSON: retain object/list types and reject duplicate decoded keys. */
    public static function decodeJson(string $data, bool $allowBom = false): mixed
    {
        if (preg_match('//u', $data) !== 1) {
            throw new UnexpectedValueException('JSON must use UTF-8');
        }
        if ($allowBom && str_starts_with($data, "\xef\xbb\xbf")) {
            $data = substr($data, 3);
        }
        $offset = 0;
        $length = strlen($data);
        $skip = static function () use (&$offset, $length, $data): void {
            while ($offset < $length && str_contains(" \t\r\n", $data[$offset])) {
                ++$offset;
            }
        };
        $string = static function () use (&$offset, $length, $data): string {
            $start = $offset++;
            while ($offset < $length) {
                $char = $data[$offset++];
                if ($char === '\\') {
                    ++$offset;
                } elseif ($char === '"') {
                    return json_decode(substr($data, $start, $offset - $start), false, 512, JSON_THROW_ON_ERROR);
                }
            }
            throw new UnexpectedValueException('unterminated JSON string');
        };
        $value = static function (int $depth = 0) use (&$value, &$offset, $length, $data, $skip, $string): mixed {
            if ($depth > 512) {
                throw new UnexpectedValueException('JSON nesting exceeds the depth limit');
            }
            $skip();
            $char = $data[$offset] ?? '';
            if ($char === '"') {
                return $string();
            }
            if ($char === '{' || $char === '[') {
                $object = $char === '{';
                $close = $object ? '}' : ']';
                $result = $object ? new stdClass() : [];
                $seen = [];
                ++$offset;
                $skip();
                if (($data[$offset] ?? '') === $close) {
                    ++$offset;
                    return $result;
                }
                while (true) {
                    $skip();
                    if ($object) {
                        if (($data[$offset] ?? '') !== '"') {
                            throw new UnexpectedValueException('JSON object key must be a string');
                        }
                        $key = $string();
                        if (isset($seen['key:' . $key])) {
                            throw new UnexpectedValueException('duplicate JSON key ' . var_export($key, true));
                        }
                        $seen['key:' . $key] = true;
                        $skip();
                        if (($data[$offset++] ?? '') !== ':') {
                            throw new UnexpectedValueException('JSON object key requires a colon');
                        }
                        $result->{$key} = $value($depth + 1);
                    } else {
                        $result[] = $value($depth + 1);
                    }
                    $skip();
                    $separator = $data[$offset++] ?? '';
                    if ($separator === $close) {
                        return $result;
                    }
                    if ($separator !== ',') {
                        throw new UnexpectedValueException('invalid JSON collection separator');
                    }
                }
            }
            if (preg_match('/\G(?:true|false|null|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/A', $data, $match, 0, $offset) !== 1) {
                throw new UnexpectedValueException('invalid JSON value');
            }
            $offset += strlen($match[0]);
            $result = json_decode($match[0], false, 512, JSON_THROW_ON_ERROR);
            if (is_float($result) && !is_finite($result)) {
                throw new UnexpectedValueException('invalid JSON number');
            }
            return $result;
        };
        $result = $value();
        $skip();
        if ($offset !== $length) {
            throw new UnexpectedValueException('trailing JSON data');
        }
        return $result;
    }

    /** Reconstruct indexed rows, including packed pixels but excluding padding. */
    private static function paletteRow(string $row, string $previous, int $filter, int $depth, int $width, int $entries): string
    {
        if ($filter !== 0) {
            for ($i = 0, $length = strlen($row); $i < $length; ++$i) {
                $left = $i === 0 ? 0 : ord($row[$i - 1]);
                $above = $previous === '' ? 0 : ord($previous[$i]);
                $upperLeft = $previous === '' || $i === 0 ? 0 : ord($previous[$i - 1]);
                if ($filter === 4) {
                    $estimate = $left + $above - $upperLeft;
                    $distances = [abs($estimate - $left), abs($estimate - $above), abs($estimate - $upperLeft)];
                    $predictor = [$left, $above, $upperLeft][array_search(min($distances), $distances, true)];
                } else {
                    $predictor = match ($filter) {
                        1 => $left, 2 => $above, 3 => intdiv($left + $above, 2),
                    };
                }
                $row[$i] = chr((ord($row[$i]) + $predictor) & 255);
            }
        }
        $perByte = intdiv(8, $depth);
        for ($pixel = 0; $pixel < $width; ++$pixel) {
            $sample = (ord($row[intdiv($pixel, $perByte)]) >> (8 - $depth * ($pixel % $perByte + 1))) & ((1 << $depth) - 1);
            if ($sample >= $entries) {
                throw new UnexpectedValueException('PNG image data references a missing palette entry');
            }
        }
        return $row;
    }

    private static function pngScanlines(array $chunks, int $width, int $height, int $depth, int $color, int $interlace, ?int $entries): void
    {
        if ($color === 3 && $entries === null) {
            throw new UnexpectedValueException('indexed PNG requires PLTE before IDAT');
        }
        $bits = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$color] * $depth;
        $passes = $interlace === 0 ? [[0, 0, 1, 1]] : [
            [0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4],
            [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2],
        ];
        $rows = [];
        $expected = 0;
        foreach ($passes as [$x, $y, $stepX, $stepY]) {
            $passWidth = max(0, intdiv($width - $x + $stepX - 1, $stepX));
            $passHeight = max(0, intdiv($height - $y + $stepY - 1, $stepY));
            if ($passWidth !== 0 && $passHeight !== 0) {
                $rowBytes = intdiv($passWidth * $bits + 7, 8);
                // Compare by division before multiplying potentially huge dimensions.
                if ($rowBytes + 1 > intdiv(self::MAX_PNG_DECOMPRESSED_BYTES - $expected, $passHeight)) {
                    throw new UnexpectedValueException('PNG image data exceeds the 128 MiB decoded-size limit');
                }
                $expected += ($rowBytes + 1) * $passHeight;
                $rows[] = [$rowBytes, $passHeight, $passWidth];
            }
        }
        $decoder = inflate_init(ZLIB_ENCODING_DEFLATE);
        $produced = 0;
        $pass = 0;
        $rowsLeft = $rows[0][1];
        $remaining = 0;
        $row = '';
        $previous = '';
        $filter = 0;
        $checkPalette = $color === 3 && $entries < (1 << $depth);
        $ended = false;
        foreach ($chunks as $chunk) {
            // A tiny compressed input block bounds each inflate allocation even
            // for malicious high-ratio data; never inflate a complete IDAT at once.
            for ($start = 0, $length = strlen($chunk); $start < $length; $start += 16) {
                $output = @inflate_add($decoder, substr($chunk, $start, 16), ZLIB_SYNC_FLUSH);
                if ($output === false) {
                    throw new UnexpectedValueException('PNG image data contains an invalid zlib stream');
                }
                $produced += strlen($output);
                if ($produced > $expected) {
                    throw new UnexpectedValueException('PNG image data has excess decompressed scanline bytes');
                }
                for ($offset = 0, $outputLength = strlen($output); $offset < $outputLength;) {
                    if ($remaining === 0) {
                        $filter = ord($output[$offset++]);
                        if ($filter > 4) {
                            throw new UnexpectedValueException('PNG image data contains an invalid scanline filter');
                        }
                        $remaining = $rows[$pass][0];
                        $row = '';
                    }
                    $consumed = min($remaining, $outputLength - $offset);
                    if ($checkPalette) {
                        $row .= substr($output, $offset, $consumed);
                    }
                    $remaining -= $consumed;
                    $offset += $consumed;
                    if ($remaining === 0) {
                        if ($checkPalette) {
                            $previous = self::paletteRow($row, $previous, $filter, $depth, $rows[$pass][2], $entries);
                        }
                        if (--$rowsLeft === 0) {
                            ++$pass;
                            if (isset($rows[$pass])) {
                                $rowsLeft = $rows[$pass][1];
                                $previous = '';
                            }
                        }
                    }
                }
                if (inflate_get_status($decoder) === ZLIB_STREAM_END) {
                    $ended = true;
                    break 2;
                }
            }
        }
        if (!$ended) {
            throw new UnexpectedValueException('PNG image data contains an incomplete zlib stream');
        }
        if ($produced !== $expected || $pass !== count($rows) || $remaining !== 0) {
            throw new UnexpectedValueException('PNG image data has an incorrect decompressed scanline size');
        }
    }

    public static function pngMetadata(string $data): array
    {
        if (!str_starts_with($data, self::PNG_SIGNATURE)) {
            throw new UnexpectedValueException('invalid PNG signature');
        }
        $position = 8;
        $header = null;
        $entries = null;
        $transparency = false;
        $seenData = false;
        $closedData = false;
        $chunks = [];
        $seenEnd = false;
        $size = strlen($data);
        while ($position < $size) {
            if ($size - $position < 12) {
                throw new UnexpectedValueException('truncated PNG chunk');
            }
            $length = unpack('N', substr($data, $position, 4))[1];
            $kind = substr($data, $position + 4, 4);
            if (preg_match('/^[A-Za-z]{4}$/D', $kind) !== 1 || (ord($kind[2]) & 32) !== 0) {
                throw new UnexpectedValueException('invalid PNG chunk type');
            }
            $end = $position + 12 + $length;
            if ($end > $size) {
                throw new UnexpectedValueException('truncated PNG chunk payload');
            }
            $payload = substr($data, $position + 8, $length);
            if (pack('N', crc32($kind . $payload)) !== substr($data, $position + 8 + $length, 4)) {
                throw new UnexpectedValueException('invalid PNG chunk checksum');
            }
            if ($header === null && $kind !== 'IHDR') {
                throw new UnexpectedValueException('PNG must begin with IHDR');
            }
            if ((ord($kind[0]) & 32) === 0 && !in_array($kind, ['IHDR', 'PLTE', 'IDAT', 'IEND'], true)) {
                throw new UnexpectedValueException('PNG contains an unsupported critical chunk');
            }
            switch ($kind) {
                case 'IHDR':
                    if ($header !== null || $length !== 13) {
                        throw new UnexpectedValueException('invalid PNG IHDR');
                    }
                    $header = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfiltering/Cinterlace', $payload);
                    $depths = [0 => [1, 2, 4, 8, 16], 2 => [8, 16], 3 => [1, 2, 4, 8], 4 => [8, 16], 6 => [8, 16]];
                    if ($header['width'] <= 0 || $header['width'] >= 2 ** 31 || $header['height'] <= 0 || $header['height'] >= 2 ** 31 || !in_array($header['depth'], $depths[$header['color']] ?? [], true)) {
                        throw new UnexpectedValueException('invalid PNG dimensions, bit depth or color type');
                    }
                    if ($header['compression'] !== 0 || $header['filtering'] !== 0 || !in_array($header['interlace'], [0, 1], true)) {
                        throw new UnexpectedValueException('unsupported PNG header fields');
                    }
                    break;
                case 'PLTE':
                    if ($entries !== null || $seenData || $transparency) {
                        throw new UnexpectedValueException('PNG PLTE must appear once before tRNS and IDAT');
                    }
                    if (in_array($header['color'], [0, 4], true) || $length <= 0 || $length > 768 || $length % 3 !== 0) {
                        throw new UnexpectedValueException('invalid PNG PLTE palette');
                    }
                    $entries = intdiv($length, 3);
                    if ($header['color'] === 3 && $entries > (1 << $header['depth'])) {
                        throw new UnexpectedValueException('PNG PLTE exceeds the indexed bit-depth range');
                    }
                    break;
                case 'tRNS':
                    $color = $header['color'];
                    if ($transparency || $seenData) {
                        throw new UnexpectedValueException('PNG tRNS must appear once before IDAT');
                    }
                    if ($color === 3 && ($entries === null || $length <= 0 || $length > $entries)) {
                        throw new UnexpectedValueException('invalid PNG tRNS palette transparency');
                    }
                    if ((in_array($color, [0, 2], true) && $length !== [0 => 2, 2 => 6][$color]) || !in_array($color, [0, 2, 3], true)) {
                        throw new UnexpectedValueException('invalid PNG tRNS for the color type');
                    }
                    $transparency = true;
                    break;
                case 'IDAT':
                    if ($header['color'] === 3 && $entries === null) {
                        throw new UnexpectedValueException('indexed PNG requires PLTE before IDAT');
                    }
                    if ($closedData) {
                        throw new UnexpectedValueException('PNG image data IDAT chunks must be consecutive');
                    }
                    $seenData = true;
                    $chunks[] = $payload;
                    break;
                case 'IEND':
                    if ($length !== 0 || $end !== $size) {
                        throw new UnexpectedValueException('invalid PNG IEND or trailing data');
                    }
                    $seenEnd = true;
                    break 2;
            }
            if ($seenData && $kind !== 'IDAT') {
                $closedData = true;
            }
            $position = $end;
        }
        if ($header === null || !$seenData || !$seenEnd) {
            throw new UnexpectedValueException('PNG is missing IHDR, IDAT or IEND');
        }
        self::pngScanlines($chunks, $header['width'], $header['height'], $header['depth'], $header['color'], $header['interlace'], $entries);
        $mode = [0 => $header['depth'] === 1 ? '1' : ($header['depth'] === 16 ? 'I' : 'L'), 2 => 'RGB', 3 => 'P', 4 => 'LA', 6 => 'RGBA'][$header['color']];
        return ['width' => $header['width'], 'height' => $header['height'], 'mode' => $mode, 'has_alpha' => in_array($header['color'], [4, 6], true) || $transparency];
    }

    private static function checkCss(string $value): void
    {
        if (str_contains($value, '\\')) {
            throw new UnexpectedValueException('SVG CSS escapes are not permitted');
        }
        $plain = preg_replace('~/\*.*?\*/~s', '', $value);
        if (preg_match('/(?:-webkit-)?image-set\s*\(/i', $plain)) {
            throw new UnexpectedValueException('SVG CSS image-set is not permitted');
        }
        if (preg_match('/@\s*(?:import|font-face)\b/i', $plain)) {
            throw new UnexpectedValueException('SVG must not load external styles or fonts');
        }
        preg_match_all('/url\s*\((.*?)\)/is', $plain, $references);
        foreach ($references[1] as $reference) {
            $reference = trim(trim(trim($reference), "\"'"));
            if (!str_starts_with($reference, '#') || strlen($reference) === 1) {
                throw new UnexpectedValueException('SVG CSS URLs must reference local fragments');
            }
        }
    }

    public static function svgMetadata(string $data, mixed $status): array
    {
        $xml = self::utf8($data, 'SVG must use UTF-8 XML');
        if (preg_match('/<\?(?!xml(?:\s|\?>))[\s\S]*?\?>/', $xml)) {
            throw new UnexpectedValueException('SVG processing instructions are not permitted');
        }
        if ($status === 'legacy' && substr_count($xml, self::LEGACY_SVG_DOCTYPE) === 1) {
            $xml = str_replace(self::LEGACY_SVG_DOCTYPE, '', $xml);
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new UnexpectedValueException('SVG DTDs and entities are not permitted');
        }
        $document = new DOMDocument();
        $prior = libxml_use_internal_errors(true);
        try {
            try {
                $parsed = $document->loadXML($xml, LIBXML_NONET);
            } catch (\ValueError) {
                $parsed = false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
        if (!$parsed) {
            throw new UnexpectedValueException('invalid SVG XML');
        }
        if ($document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg' || $document->documentElement->localName !== 'svg') {
            throw new UnexpectedValueException('SVG must have an svg root element in the SVG namespace');
        }
        $embedded = false;
        $forbidden = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'font-face-src', 'font-face-uri', 'animate', 'animatemotion', 'animatetransform', 'set', 'discard'];
        foreach ($document->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->localName);
            if (in_array($tag, $forbidden, true)) {
                throw new UnexpectedValueException("SVG contains forbidden {$tag} element");
            }
            if ($tag === 'style') {
                self::checkCss($element->textContent);
            }
            $sources = [];
            foreach ($element->attributes as $attributeNode) {
                $attribute = strtolower($attributeNode->localName);
                $value = $attributeNode->value;
                if (str_starts_with($attribute, 'on')) {
                    throw new UnexpectedValueException('SVG event handlers are not permitted');
                }
                if (in_array($attribute, ['href', 'src'], true)) {
                    if ($tag === 'image') {
                        $sources[] = $value;
                    } elseif (!str_starts_with($value, '#') || strlen($value) === 1) {
                        throw new UnexpectedValueException('SVG references must use local fragments');
                    }
                }
                if ($attribute === 'base') {
                    throw new UnexpectedValueException('SVG must not set an external base URI');
                }
                // Presentation attributes use CSS; inspect escapes before URL detection.
                if ($attribute === 'style' || str_contains($value, '\\') || preg_match('/(?:url|image-set)\s*\(/i', $value)) {
                    self::checkCss($value);
                }
            }
            if ($tag === 'image') {
                if ($sources === []) {
                    throw new UnexpectedValueException('SVG image is missing its embedded raster source');
                }
                foreach ($sources as $source) {
                    if (!preg_match('/^data:([^;,]+);base64,([\s\S]+)$/iD', $source, $match)) {
                        throw new UnexpectedValueException('SVG images must use embedded base64 raster data');
                    }
                    if (strtolower($match[1]) !== 'image/png') {
                        throw new UnexpectedValueException('SVG embedded raster format has no decoder; only image/png is supported');
                    }
                    $base64 = preg_replace('/\s/', '', $match[2]);
                    // Check alphabet and padding without a repeated regex group:
                    // large historical payloads can exhaust PCRE's JIT stack.
                    $payload = base64_decode($base64, true);
                    $unpadded = rtrim($base64, '=');
                    $padding = strlen($base64) - strlen($unpadded);
                    if ($payload === false || strlen($base64) % 4 !== 0 || $padding > 2 || strspn($unpadded, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/') !== strlen($unpadded)) {
                        throw new UnexpectedValueException('SVG contains invalid base64 raster data');
                    }
                    if ($payload === '') {
                        throw new UnexpectedValueException('SVG contains empty embedded raster data');
                    }
                    try {
                        self::pngMetadata($payload);
                    } catch (UnexpectedValueException $error) {
                        throw new UnexpectedValueException('SVG embedded PNG is invalid: ' . $error->getMessage(), 0, $error);
                    }
                }
                $embedded = true;
            }
        }
        return ['embedded_raster' => $embedded];
    }

    private static function cssSidecar(string $data, string $root, string $relative): void
    {
        $css = self::utf8($data, 'CSS sidecars must use UTF-8');
        if (str_contains($css, '\\')) {
            throw new UnexpectedValueException('CSS sidecar escapes are not permitted');
        }
        $plain = preg_replace('~/\*.*?\*/~s', '', $css);
        if (preg_match('/(?:-webkit-)?image-set\s*\(/i', $plain)) {
            throw new UnexpectedValueException('CSS sidecar image-set is not permitted');
        }
        if (preg_match('/@\s*(?:import|font-face)\b/i', $plain)) {
            throw new UnexpectedValueException('CSS sidecars must not load external styles or fonts');
        }
        preg_match_all('/url\s*\((.*?)\)/is', $plain, $references);
        foreach ($references[1] as $reference) {
            $reference = trim(trim(trim($reference), "\"'"));
            $parsed = parse_url($reference);
            if ($parsed === false) {
                throw new UnexpectedValueException('CSS sidecar contains an invalid URL');
            }
            if (isset($parsed['scheme']) || isset($parsed['host']) || str_starts_with($parsed['path'] ?? '', '/')) {
                throw new UnexpectedValueException('CSS sidecar URLs must be repository-relative');
            }
            if (($parsed['path'] ?? '') === '') {
                if (($parsed['fragment'] ?? '') !== '') {
                    continue;
                }
                throw new UnexpectedValueException('CSS sidecar contains an empty URL');
            }
            $parent = dirname($relative);
            $joined = ($parent === '.' ? '' : $parent . '/') . rawurldecode($parsed['path']);
            // Pure relative URL joining removes ./, but retains ../ for rejection.
            $targetRelative = implode('/', array_values(array_filter(explode('/', $joined), static fn (string $part): bool => $part !== '.')));
            [$target, $error] = self::safePath($root, $targetRelative);
            if ($error !== null) {
                throw new UnexpectedValueException('CSS sidecar URL is unsafe: ' . $error);
            }
            if (!is_file($target)) {
                throw new UnexpectedValueException('CSS sidecar URL points to a missing file: ' . $targetRelative);
            }
        }
    }

    private static function jsonSidecar(string $data): void
    {
        try {
            $document = self::decodeJson($data, true);
        } catch (\Throwable $error) {
            throw new UnexpectedValueException('JSON sidecar must contain valid UTF-8 JSON (' . $error->getMessage() . ')', 0, $error);
        }
        if (!$document instanceof stdClass && !is_array($document)) {
            throw new UnexpectedValueException('JSON sidecar must contain an object or list');
        }
    }

    private static function managedImages(string $root, array &$errors): array
    {
        $images = [];
        $walk = static function (string $directory) use (&$walk, $root, &$errors, &$images): void {
            $absolute = $root . '/' . $directory;
            if (is_link($absolute)) {
                $errors[] = $directory . ': managed directory must not be a symlink';
                return;
            }
            if (!file_exists($absolute)) {
                return;
            }
            $children = @scandir($absolute);
            if ($children === false) {
                $errors[] = $directory . ': could not inspect managed directory';
                return;
            }
            foreach ($children as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $relative = $directory . '/' . $name;
                $path = $root . '/' . $relative;
                if (is_dir($path)) {
                    $walk($relative);
                } elseif (isset(self::IMAGE_FORMATS[strtolower(pathinfo($name, PATHINFO_EXTENSION))])) {
                    $images[$relative] = true;
                }
            }
        };
        $walk('assets');
        $walk('references');
        return $images;
    }

    private static function nonempty(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function digest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-fA-F]{64}$/D', $value) === 1;
    }

    private static function format(string $relative): ?string
    {
        return (self::IMAGE_FORMATS + ['css' => 'css', 'json' => 'json'])[strtolower(pathinfo($relative, PATHINFO_EXTENSION))] ?? null;
    }

    private static function declaredFormat(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower($value) : null;
        return $value === 'jpg' ? 'jpeg' : $value;
    }

    private static function provenanceErrors(mixed $provenance, string $label, array &$errors): void
    {
        if (!$provenance instanceof stdClass || !self::nonempty($provenance->source ?? null)) {
            $errors[] = $label . ': provenance.source must be a nonempty string';
        }
        if (!$provenance instanceof stdClass || !self::digest($provenance->source_sha256 ?? null)) {
            $errors[] = $label . ': provenance.source_sha256 must be a SHA-256 digest';
        }
    }

    private static function identifierErrors(mixed $identifier, string $label, array &$ids, array &$errors): void
    {
        if (!self::nonempty($identifier)) {
            $errors[] = $label . ': id must be a nonempty string';
        } elseif (isset($ids['id:' . $identifier])) {
            $errors[] = $label . ': duplicate id ' . var_export($identifier, true);
        } else {
            $ids['id:' . $identifier] = true;
        }
    }

    private static function archivedSourceErrors(mixed $sources, array &$ids, array &$errors): void
    {
        if (!is_array($sources)) {
            $errors[] = 'assets/manifest.json: archived_sources must be a list';
            return;
        }
        foreach ($sources as $index => $source) {
            $label = "archived_sources[{$index}]";
            if (!$source instanceof stdClass) {
                $errors[] = $label . ': archived source must be an object';
                continue;
            }
            self::identifierErrors($source->id ?? null, $label, $ids, $errors);
            if (($source->availability ?? null) !== 'local-backup-not-distributed') {
                $errors[] = $label . ': availability must be local-backup-not-distributed';
            }
            $relative = $source->path ?? null;
            if (($error = self::relativePathError($relative)) !== null) {
                $errors[] = $label . ': ' . $error;
            } elseif (self::format($relative) === null || self::declaredFormat($source->format ?? null) !== self::format($relative)) {
                $errors[] = $label . ': format must match a supported asset extension';
            }
            if (property_exists($source, 'archive_path') && ($error = self::relativePathError($source->archive_path)) !== null) {
                $errors[] = $label . ': archive_path ' . $error;
            }
            if (!self::digest($source->sha256 ?? null)) {
                $errors[] = $label . ': sha256 must be a SHA-256 digest';
            }
            if (!is_int($source->bytes ?? null) || $source->bytes < 0) {
                $errors[] = $label . ': bytes must be a nonnegative integer';
            }
            self::provenanceErrors($source->provenance ?? null, $label, $errors);
        }
    }

    private static function sourceIdErrors(array $collections, array $ids, array &$errors): void
    {
        foreach ($collections as $collection => $records) {
            if (!is_array($records)) {
                continue;
            }
            foreach ($records as $index => $record) {
                if (!$record instanceof stdClass || !($record->provenance ?? null) instanceof stdClass || !property_exists($record->provenance, 'source_ids')) {
                    continue;
                }
                $sources = $record->provenance->source_ids;
                $label = "{$collection}[{$index}]";
                if (!is_array($sources)) {
                    $errors[] = $label . ': provenance.source_ids must be a list';
                    continue;
                }
                foreach ($sources as $identifier) {
                    if (!self::nonempty($identifier)) {
                        $errors[] = $label . ': source id must be a nonempty string';
                    } elseif (!isset($ids['id:' . $identifier])) {
                        $errors[] = $label . ': unknown source id ' . var_export($identifier, true);
                    }
                }
            }
        }
    }

    /** @return array{valid: bool, checked: int, errors: list<string>} */
    public static function validate(string $root): array
    {
        $errors = [];
        $checked = 0;
        $root = realpath($root);
        if ($root === false || !is_dir($root)) {
            return ['valid' => false, 'checked' => 0, 'errors' => ['repository root must be an existing directory']];
        }
        [$manifestPath, $error] = self::safePath($root, 'assets/manifest.json');
        if ($error !== null) {
            return ['valid' => false, 'checked' => 0, 'errors' => ['assets/manifest.json: ' . $error]];
        }
        try {
            $data = @file_get_contents($manifestPath);
            if ($data === false) {
                throw new RuntimeException('file cannot be read');
            }
            $manifest = self::decodeJson($data);
        } catch (\Throwable $exception) {
            return ['valid' => false, 'checked' => 0, 'errors' => ['assets/manifest.json: cannot read valid JSON (' . $exception->getMessage() . ')']];
        }
        if (!$manifest instanceof stdClass || ($manifest->schema_version ?? null) !== 1) {
            return ['valid' => false, 'checked' => 0, 'errors' => ['assets/manifest.json: schema_version must be 1']];
        }
        $assets = $manifest->assets ?? null;
        if (!is_array($assets)) {
            return ['valid' => false, 'checked' => 0, 'errors' => ['assets/manifest.json: assets must be a list']];
        }
        $ids = [];
        $paths = [];
        foreach ($assets as $index => $asset) {
            $label = "assets[{$index}]";
            if (!$asset instanceof stdClass) {
                $errors[] = $label . ': asset must be an object';
                continue;
            }
            self::identifierErrors($asset->id ?? null, $label, $ids, $errors);
            $relative = $asset->path ?? null;
            [$path, $error] = self::safePath($root, $relative);
            if ($error !== null) {
                $errors[] = $label . ': ' . $error;
                continue;
            }
            $label = $relative;
            if (isset($paths[$relative])) {
                $errors[] = $label . ': duplicate path';
            }
            $paths[$relative] = true;
            foreach (['category', 'role', 'rights'] as $field) {
                if (!self::nonempty($asset->{$field} ?? null)) {
                    $errors[] = "{$label}: {$field} must be a nonempty string";
                }
            }
            if (!in_array($asset->status ?? null, self::STATUSES, true)) {
                $errors[] = $label . ': invalid status';
            }
            $provenance = $asset->provenance ?? null;
            self::provenanceErrors($provenance, $label, $errors);
            $digest = $asset->sha256 ?? null;
            if (!self::digest($digest)) {
                $errors[] = $label . ': sha256 must be a SHA-256 digest';
            }
            $size = $asset->bytes ?? null;
            if (!is_int($size) || $size < 0) {
                $errors[] = $label . ': bytes must be a nonnegative integer';
            }
            $format = self::format($relative);
            if ($format === null || self::declaredFormat($asset->format ?? null) !== $format) {
                $errors[] = $label . ': format must match a supported asset extension';
            }
            if ($format !== null && $format !== 'svg' && in_array($format, self::IMAGE_FORMATS, true)) {
                foreach (['width', 'height'] as $field) {
                    if (!is_int($asset->{$field} ?? null) || $asset->{$field} <= 0) {
                        $errors[] = "{$label}: {$field} must be a positive integer";
                    }
                }
                if (!self::nonempty($asset->mode ?? null)) {
                    $errors[] = $label . ': mode must be a nonempty string';
                }
                if (!is_bool($asset->has_alpha ?? null)) {
                    $errors[] = $label . ': has_alpha must be a boolean';
                }
            }
            if ($format === 'svg' && !is_bool($asset->embedded_raster ?? null)) {
                $errors[] = $label . ': embedded_raster must be a boolean';
            }
            if (!is_file($path)) {
                $errors[] = $label . ': file is missing or is not a regular file';
                continue;
            }
            $data = @file_get_contents($path);
            if ($data === false) {
                $errors[] = $label . ': cannot read file';
                continue;
            }
            ++$checked;
            if ($size !== strlen($data)) {
                $errors[] = $label . ': byte count mismatch';
            }
            $actualDigest = hash('sha256', $data);
            if (!is_string($digest) || strtolower($digest) !== $actualDigest) {
                $errors[] = $label . ': SHA-256 mismatch';
            }
            if ($provenance instanceof stdClass && ($provenance->source ?? null) === 'repository-authored:' . $relative) {
                if (!is_string($provenance->source_sha256 ?? null) || strtolower($provenance->source_sha256) !== $actualDigest) {
                    $errors[] = $label . ': repository-authored self-source SHA-256 must match the delivered bytes';
                }
            }
            try {
                if ($format !== null && !in_array($format, ['png', 'svg', 'css', 'json'], true)) {
                    throw new UnexpectedValueException("active format {$format} has no decoder; use a validated PNG export or archived_sources metadata");
                }
                if ($format === 'png') {
                    foreach (self::pngMetadata($data) as $key => $actual) {
                        if (property_exists($asset, $key) && $asset->{$key} !== $actual) {
                            $errors[] = "{$label}: {$key} mismatch";
                        }
                    }
                } elseif ($format === 'svg') {
                    $metadata = self::svgMetadata($data, $asset->status ?? null);
                    if (($asset->embedded_raster ?? null) !== $metadata['embedded_raster']) {
                        $errors[] = $label . ': embedded_raster must match the SVG contents';
                    }
                    if ($metadata['embedded_raster'] && ($asset->status ?? null) !== 'legacy') {
                        $errors[] = $label . ': embedded raster SVG wrappers must have legacy status';
                    }
                } elseif ($format === 'css') {
                    self::cssSidecar($data, $root, $relative);
                } elseif ($format === 'json') {
                    self::jsonSidecar($data);
                }
            } catch (\Throwable $exception) {
                $errors[] = $label . ': ' . $exception->getMessage();
            }
        }
        $archives = property_exists($manifest, 'archived_sources') ? $manifest->archived_sources : [];
        self::archivedSourceErrors($archives, $ids, $errors);
        self::sourceIdErrors(['assets' => $assets, 'archived_sources' => $archives], $ids, $errors);
        $unlisted = array_diff_key(self::managedImages($root, $errors), $paths);
        ksort($unlisted);
        foreach ($unlisted as $relative => $_) {
            $errors[] = $relative . ': managed image has no manifest entry';
        }
        return ['valid' => $errors === [], 'checked' => $checked, 'errors' => $errors];
    }
}
