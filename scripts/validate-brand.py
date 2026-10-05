#!/usr/bin/env python3
"""Check the public brand inventory using only the Python standard library."""

from __future__ import annotations

import argparse
import base64
import binascii
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import struct
import sys
from urllib.parse import unquote, urlsplit
import xml.etree.ElementTree as ET
import zlib


STATUSES = {"canonical", "reference", "legacy", "package-variant", "exploratory"}
IMAGE_FORMATS = {
    ".png": "png", ".jpg": "jpeg", ".jpeg": "jpeg", ".svg": "svg",
    ".webp": "webp", ".gif": "gif", ".avif": "avif", ".ico": "ico",
    ".bmp": "bmp", ".tif": "tiff", ".tiff": "tiff",
}
ASSET_FORMATS = {**IMAGE_FORMATS, ".css": "css", ".json": "json"}
# Keep all known image extensions in coverage and archived metadata. Active
# files must additionally have an implemented validator, rather than succeeding
# merely because their hash and claimed metadata match.
VALIDATED_FORMATS = {"png", "svg", "css", "json"}
SHA256 = re.compile(r"[0-9a-fA-F]{64}\Z")
PNG_SIGNATURE = b"\x89PNG\r\n\x1a\n"
# A delivery limit, checked before inflation; decoding uses 64 KiB output blocks.
MAX_PNG_DECOMPRESSED_BYTES = 128 * 1024 * 1024
PNG_DECODE_BLOCK_BYTES = 64 * 1024
LEGACY_SVG_DOCTYPE = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
RASTER_MIMES = {"image/png"}


def _integer(value: object) -> bool:
    return isinstance(value, int) and not isinstance(value, bool)


def _relative_path_error(value: object) -> str | None:
    """Validate a historical or delivered path without touching the filesystem."""
    if not isinstance(value, str) or not value or "\\" in value:
        return "path must be a nonempty repository-relative POSIX path"
    if any(ord(character) < 32 for character in value):
        return "path contains a control character"
    relative = PurePosixPath(value)
    if relative.is_absolute() or any(part in {"", ".", ".."} for part in value.split("/")):
        return "path must not be absolute or contain empty, . or .. components"
    if ":" in relative.parts[0]:
        return "path must not use an absolute drive or URL prefix"
    return None


def _safe_path(root: Path, value: object) -> tuple[Path | None, str | None]:
    """Reject aliases and symlinks before reading any manifest-controlled file."""
    path_error = _relative_path_error(value)
    if path_error:
        return None, path_error
    relative = PurePosixPath(value)
    current = root
    for part in relative.parts:
        current /= part
        if current.is_symlink():
            return None, "path must not traverse a symlink"
    try:
        current.resolve().relative_to(root)
    except (OSError, RuntimeError, ValueError):
        return None, "path escapes the repository root"
    return current, None


def _png_palette_row(row: bytearray, previous: bytearray, method: int, depth: int, width: int, invalid_indices: bytes) -> bytearray:
    """Reconstruct one indexed row and reject values outside its actual PLTE.

    Only a current and previous row are retained, and only for palettes smaller
    than the bit-depth range. Packed padding bits are not interpreted as pixels.
    """
    if method:
        for index in range(len(row)):
            left = row[index - 1] if index else 0
            above = previous[index] if previous else 0
            upper_left = previous[index - 1] if previous and index else 0
            if method == 1:
                predictor = left
            elif method == 2:
                predictor = above
            elif method == 3:
                predictor = (left + above) // 2
            else:
                estimate = left + above - upper_left
                distances = (abs(estimate - left), abs(estimate - above), abs(estimate - upper_left))
                predictor = (left, above, upper_left)[distances.index(min(distances))]
            row[index] = (row[index] + predictor) & 0xFF
    pixels_per_byte = 8 // depth
    complete_bytes, remaining_pixels = divmod(width, pixels_per_byte)
    for start in range(0, complete_bytes, PNG_DECODE_BLOCK_BYTES):
        block = row[start:min(start + PNG_DECODE_BLOCK_BYTES, complete_bytes)]
        if 1 in block.translate(invalid_indices):
            raise ValueError("PNG image data references a missing palette entry")
    if remaining_pixels:
        # A full-byte lookup would incorrectly reject arbitrary padding bits.
        last = row[complete_bytes]
        for pixel in range(remaining_pixels):
            sample = (last >> (8 - depth * (pixel + 1))) & ((1 << depth) - 1)
            if invalid_indices[sample << (8 - depth)]:
                raise ValueError("PNG image data references a missing palette entry")
    return row


def _png_scanlines(chunks: list[bytes], width: int, height: int, depth: int, color: int, interlace: int, palette_entries: int | None = None) -> None:
    """Check the zlib stream, filtered rows and indexed-color palette bounds.

    PNG image layout/Adam7: https://www.w3.org/TR/png-3/#8Interlace
    Filters: https://www.w3.org/TR/png-3/#9Filters
    Palette bounds: https://www.w3.org/TR/png-3/#11PLTE
    """
    if color == 3 and palette_entries is None:
        raise ValueError("indexed PNG requires PLTE before IDAT")
    bits_per_pixel = {0: 1, 2: 3, 3: 1, 4: 2, 6: 4}[color] * depth
    passes = ((0, 0, 1, 1),) if not interlace else (
        (0, 0, 8, 8), (4, 0, 8, 8), (0, 4, 4, 8), (2, 0, 4, 4),
        (0, 2, 2, 4), (1, 0, 2, 2), (0, 1, 1, 2),
    )
    rows = []
    for x_start, y_start, x_step, y_step in passes:
        pass_width = max(0, (width - x_start + x_step - 1) // x_step)
        pass_height = max(0, (height - y_start + y_step - 1) // y_step)
        if pass_width and pass_height:
            rows.append(((pass_width * bits_per_pixel + 7) // 8, pass_height, pass_width))
    expected = sum((row_bytes + 1) * count for row_bytes, count, _ in rows)
    if expected > MAX_PNG_DECOMPRESSED_BYTES:
        raise ValueError("PNG image data exceeds the 128 MiB decoded-size limit")
    decoder = zlib.decompressobj()
    produced = 0
    pass_index = 0
    rows_left = rows[0][1]
    row_remaining = 0
    check_palette = color == 3 and palette_entries < (1 << depth)
    invalid_indices = bytes(
        any(((value >> shift) & ((1 << depth) - 1)) >= palette_entries for shift in range(8 - depth, -1, -depth))
        for value in range(256)
    ) if check_palette else b""
    row = bytearray()
    previous = bytearray()
    filter_type = 0
    try:
        for chunk in chunks:
            for start in range(0, len(chunk), PNG_DECODE_BLOCK_BYTES):
                pending = chunk[start:start + PNG_DECODE_BLOCK_BYTES]
                while pending and not decoder.eof:
                    # Never pass zero: zlib interprets max_length=0 as unlimited.
                    output = decoder.decompress(pending, min(PNG_DECODE_BLOCK_BYTES, expected - produced + 1))
                    pending = decoder.unconsumed_tail
                    produced += len(output)
                    if produced > expected:
                        raise ValueError("PNG image data has excess decompressed scanline bytes")
                    offset = 0
                    while offset < len(output):
                        if row_remaining == 0:
                            if output[offset] > 4:
                                raise ValueError("PNG image data contains an invalid scanline filter")
                            filter_type = output[offset]
                            offset += 1
                            row_remaining = rows[pass_index][0]
                            if check_palette:
                                row = bytearray()
                        consumed = min(row_remaining, len(output) - offset)
                        if check_palette:
                            row.extend(output[offset:offset + consumed])
                        row_remaining -= consumed
                        offset += consumed
                        if row_remaining == 0:
                            if check_palette:
                                previous = _png_palette_row(row, previous, filter_type, depth, rows[pass_index][2], invalid_indices)
                            rows_left -= 1
                            if rows_left == 0:
                                pass_index += 1
                                if pass_index < len(rows):
                                    rows_left = rows[pass_index][1]
                                    previous = bytearray()
                if decoder.eof:
                    # PNG decoders ignore unused bytes after the zlib stream.
                    # CRCs and the asset hash still cover all delivered bytes.
                    break
            if decoder.eof:
                break
    except zlib.error as error:
        raise ValueError("PNG image data contains an invalid zlib stream") from error
    if not decoder.eof:
        raise ValueError("PNG image data contains an incomplete zlib stream")
    if produced != expected or pass_index != len(rows) or row_remaining:
        raise ValueError("PNG image data has an incorrect decompressed scanline size")


def _png_metadata(data: bytes) -> dict[str, object]:
    if not data.startswith(PNG_SIGNATURE):
        raise ValueError("invalid PNG signature")
    position = len(PNG_SIGNATURE)
    header = None
    palette_entries = None
    transparency = False
    seen_image_data = False
    image_data_closed = False
    image_chunks = []
    seen_end = False
    while position < len(data):
        if len(data) - position < 12:
            raise ValueError("truncated PNG chunk")
        length = struct.unpack_from(">I", data, position)[0]
        kind = data[position + 4:position + 8]
        if not re.fullmatch(rb"[A-Za-z]{4}", kind) or kind[2] & 0x20:
            raise ValueError("invalid PNG chunk type")
        end = position + 12 + length
        if end > len(data):
            raise ValueError("truncated PNG chunk payload")
        payload = data[position + 8:position + 8 + length]
        checksum = struct.unpack_from(">I", data, position + 8 + length)[0]
        if zlib.crc32(kind + payload) & 0xFFFFFFFF != checksum:
            raise ValueError("invalid PNG chunk checksum")
        if header is None and kind != b"IHDR":
            raise ValueError("PNG must begin with IHDR")
        if not kind[0] & 0x20 and kind not in {b"IHDR", b"PLTE", b"IDAT", b"IEND"}:
            raise ValueError("PNG contains an unsupported critical chunk")
        if kind == b"IHDR":
            if header is not None or length != 13:
                raise ValueError("invalid PNG IHDR")
            header = struct.unpack(">IIBBBBB", payload)
        elif kind == b"PLTE":
            color, depth = header[3], header[2]
            if palette_entries is not None or seen_image_data or transparency:
                raise ValueError("PNG PLTE must appear once before tRNS and IDAT")
            if color in {0, 4} or not 0 < length <= 768 or length % 3:
                raise ValueError("invalid PNG PLTE palette")
            palette_entries = length // 3
            if color == 3 and palette_entries > (1 << depth):
                raise ValueError("PNG PLTE exceeds the indexed bit-depth range")
        elif kind == b"tRNS":
            color = header[3]
            if transparency or seen_image_data:
                raise ValueError("PNG tRNS must appear once before IDAT")
            if color == 3 and (palette_entries is None or length > palette_entries):
                raise ValueError("invalid PNG tRNS palette transparency")
            if (color in {0, 2} and length != {0: 2, 2: 6}[color]) or color not in {0, 2, 3}:
                raise ValueError("invalid PNG tRNS for the color type")
            transparency = True
        elif kind == b"IDAT":
            if header[3] == 3 and palette_entries is None:
                raise ValueError("indexed PNG requires PLTE before IDAT")
            if image_data_closed:
                raise ValueError("PNG image data IDAT chunks must be consecutive")
            seen_image_data = True
            image_chunks.append(payload)
        elif kind == b"IEND":
            if length != 0 or end != len(data):
                raise ValueError("invalid PNG IEND or trailing data")
            seen_end = True
            break
        if seen_image_data and kind != b"IDAT":
            image_data_closed = True
        position = end
    if header is None or not seen_image_data or not seen_end:
        raise ValueError("PNG is missing IHDR, IDAT or IEND")
    width, height, depth, color, compression, filtering, interlace = header
    depths = {0: {1, 2, 4, 8, 16}, 2: {8, 16}, 3: {1, 2, 4, 8}, 4: {8, 16}, 6: {8, 16}}
    if not 0 < width < 2 ** 31 or not 0 < height < 2 ** 31 or depth not in depths.get(color, set()):
        raise ValueError("invalid PNG dimensions, bit depth or color type")
    if compression != 0 or filtering != 0 or interlace not in {0, 1}:
        raise ValueError("unsupported PNG header fields")
    _png_scanlines(image_chunks, width, height, depth, color, interlace, palette_entries)
    modes = {0: "1" if depth == 1 else "I" if depth == 16 else "L", 2: "RGB", 3: "P", 4: "LA", 6: "RGBA"}
    return {"width": width, "height": height, "mode": modes[color], "has_alpha": color in {4, 6} or transparency}


def _local_name(name: str) -> str:
    return name.rsplit("}", 1)[-1].lower()


def _check_css(value: str) -> None:
    # CSS escapes can disguise url()/@import. They are unnecessary in these assets.
    if "\\" in value:
        raise ValueError("SVG CSS escapes are not permitted")
    plain = re.sub(r"/\*.*?\*/", "", value, flags=re.DOTALL)
    if re.search(r"(?:-webkit-)?image-set\s*\(", plain, flags=re.IGNORECASE):
        raise ValueError("SVG CSS image-set is not permitted")
    if re.search(r"@\s*(?:import|font-face)\b", plain, flags=re.IGNORECASE):
        raise ValueError("SVG must not load external styles or fonts")
    for reference in re.findall(r"url\s*\((.*?)\)", plain, flags=re.IGNORECASE | re.DOTALL):
        reference = reference.strip().strip("\"'").strip()
        if not reference.startswith("#") or len(reference) == 1:
            raise ValueError("SVG CSS URLs must reference local fragments")


def _svg_metadata(data: bytes, status: object) -> dict[str, object]:
    try:
        xml = data.decode("utf-8-sig")
    except UnicodeError as error:
        raise ValueError("SVG must use UTF-8 XML") from error
    if re.search(r"<\?(?!xml(?:\s|\?>))[\s\S]*?\?>", xml):
        raise ValueError("SVG processing instructions are not permitted")
    if status == "legacy" and xml.count(LEGACY_SVG_DOCTYPE) == 1:
        # ElementTree never needs the external DTD. Remove this historical marker
        # before parsing, while preserving the original asset bytes and digest.
        xml = xml.replace(LEGACY_SVG_DOCTYPE, "", 1)
    if re.search(r"<!\s*(?:DOCTYPE|ENTITY)\b", xml, flags=re.IGNORECASE):
        raise ValueError("SVG DTDs and entities are not permitted")
    try:
        document = ET.fromstring(xml)
    except (ET.ParseError, ValueError) as error:
        raise ValueError("invalid SVG XML") from error
    if _local_name(document.tag) != "svg":
        raise ValueError("SVG must have an svg root element")
    embedded = False
    forbidden = {
        "script", "foreignobject", "iframe", "object", "embed", "audio", "video",
        "font-face-src", "font-face-uri", "animate", "animatemotion", "animatetransform", "set", "discard",
    }
    for element in document.iter():
        tag = _local_name(element.tag)
        if tag in forbidden:
            raise ValueError(f"SVG contains forbidden {tag} element")
        if tag == "style":
            _check_css("".join(element.itertext()))
        image_sources = []
        for name, value in element.attrib.items():
            attribute = _local_name(name)
            if attribute.startswith("on"):
                raise ValueError("SVG event handlers are not permitted")
            if attribute in {"href", "src"}:
                if tag == "image":
                    image_sources.append(value)
                elif not value.startswith("#") or len(value) == 1:
                    raise ValueError("SVG references must use local fragments")
            if attribute == "base":
                raise ValueError("SVG must not set an external base URI")
            if attribute == "style" or re.search(r"(?:url|image-set)\s*\(", value, flags=re.IGNORECASE):
                _check_css(value)
        if tag == "image":
            if not image_sources:
                raise ValueError("SVG image is missing its embedded raster source")
            for source in image_sources:
                match = re.fullmatch(r"data:([^;,]+);base64,([\s\S]+)", source, flags=re.IGNORECASE)
                if not match:
                    raise ValueError("SVG images must use embedded base64 raster data")
                if match.group(1).lower() not in RASTER_MIMES:
                    raise ValueError("SVG embedded raster format has no decoder; only image/png is supported")
                try:
                    payload = base64.b64decode("".join(match.group(2).split()), validate=True)
                except (ValueError, binascii.Error) as error:
                    raise ValueError("SVG contains invalid base64 raster data") from error
                if not payload:
                    raise ValueError("SVG contains empty embedded raster data")
                try:
                    _png_metadata(payload)
                except ValueError as error:
                    raise ValueError(f"SVG embedded PNG is invalid: {error}") from error
            embedded = True
    return {"embedded_raster": embedded}


def _css_sidecar(data: bytes, root: Path, relative: str) -> None:
    try:
        css = data.decode("utf-8-sig")
    except UnicodeError as error:
        raise ValueError("CSS sidecars must use UTF-8") from error
    if "\\" in css:
        raise ValueError("CSS sidecar escapes are not permitted")
    plain = re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)
    if re.search(r"(?:-webkit-)?image-set\s*\(", plain, flags=re.IGNORECASE):
        raise ValueError("CSS sidecar image-set is not permitted")
    if re.search(r"@\s*(?:import|font-face)\b", plain, flags=re.IGNORECASE):
        raise ValueError("CSS sidecars must not load external styles or fonts")
    for reference in re.findall(r"url\s*\((.*?)\)", plain, flags=re.IGNORECASE | re.DOTALL):
        reference = reference.strip().strip("\"'").strip()
        try:
            parsed = urlsplit(reference)
        except ValueError as error:
            raise ValueError("CSS sidecar contains an invalid URL") from error
        if parsed.scheme or parsed.netloc or parsed.path.startswith("/"):
            raise ValueError("CSS sidecar URLs must be repository-relative")
        if not parsed.path:
            if parsed.fragment:
                continue
            raise ValueError("CSS sidecar contains an empty URL")
        target_relative = (PurePosixPath(relative).parent / unquote(parsed.path)).as_posix()
        target, error = _safe_path(root, target_relative)
        if error:
            raise ValueError(f"CSS sidecar URL is unsafe: {error}")
        if not target.is_file():
            raise ValueError(f"CSS sidecar URL points to a missing file: {target_relative}")


def _unique_json_object(pairs: list[tuple[str, object]]) -> dict[str, object]:
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"duplicate JSON key {key!r}")
        result[key] = value
    return result


def _invalid_json_constant(value: str) -> None:
    raise ValueError(f"invalid JSON constant {value}")


def _json_sidecar(data: bytes) -> None:
    try:
        document = json.loads(data.decode("utf-8-sig"), parse_constant=_invalid_json_constant,
                              object_pairs_hook=_unique_json_object)
    except (UnicodeError, json.JSONDecodeError, ValueError) as error:
        raise ValueError(f"JSON sidecar must contain valid UTF-8 JSON ({error})") from error
    if not isinstance(document, (dict, list)):
        raise ValueError("JSON sidecar must contain an object or list")


def _managed_images(root: Path, errors: list[str]) -> set[str]:
    images = set()
    for directory in ("assets", "references"):
        base = root / directory
        if base.is_symlink():
            errors.append(f"{directory}: managed directory must not be a symlink")
            continue
        if not base.exists():
            continue
        def on_error(error: OSError) -> None:
            errors.append(f"{directory}: could not inspect managed directory ({error.strerror})")
        for current, directories, files in os.walk(base, followlinks=False, onerror=on_error):
            current_path = Path(current)
            for name in list(directories):
                path = current_path / name
                if path.is_symlink():
                    errors.append(f"{path.relative_to(root).as_posix()}: managed directory must not be a symlink")
                    directories.remove(name)
            for name in files:
                path = current_path / name
                if path.suffix.lower() in IMAGE_FORMATS:
                    images.add(path.relative_to(root).as_posix())
    return images


def _archived_source_errors(sources: object, ids: set[str], errors: list[str]) -> None:
    """Check historical metadata only; a local backup is never required or read.

    Historical paths may coincide with a replacement's active delivery path.
    They are not added to managed-image coverage or the checked-file count.
    """
    if not isinstance(sources, list):
        errors.append("assets/manifest.json: archived_sources must be a list")
        return
    for index, source in enumerate(sources):
        label = f"archived_sources[{index}]"
        if not isinstance(source, dict):
            errors.append(f"{label}: archived source must be an object")
            continue
        identifier = source.get("id")
        if not isinstance(identifier, str) or not identifier.strip():
            errors.append(f"{label}: id must be a nonempty string")
        elif identifier in ids:
            errors.append(f"{label}: duplicate id {identifier!r}")
        else:
            ids.add(identifier)
        if source.get("availability") != "local-backup-not-distributed":
            errors.append(f"{label}: availability must be local-backup-not-distributed")
        relative = source.get("path")
        path_error = _relative_path_error(relative)
        if path_error:
            errors.append(f"{label}: {path_error}")
        else:
            expected_format = ASSET_FORMATS.get(PurePosixPath(relative).suffix.lower())
            declared_format = source.get("format")
            normalized_format = declared_format.lower() if isinstance(declared_format, str) else None
            if normalized_format == "jpg":
                normalized_format = "jpeg"
            if expected_format is None or normalized_format != expected_format:
                errors.append(f"{label}: format must match a supported asset extension")
        if "archive_path" in source:
            archive_error = _relative_path_error(source["archive_path"])
            if archive_error:
                errors.append(f"{label}: archive_path {archive_error}")
        digest = source.get("sha256")
        if not isinstance(digest, str) or not SHA256.fullmatch(digest):
            errors.append(f"{label}: sha256 must be a SHA-256 digest")
        size = source.get("bytes")
        if not _integer(size) or size < 0:
            errors.append(f"{label}: bytes must be a nonnegative integer")
        provenance = source.get("provenance")
        if not isinstance(provenance, dict) or not isinstance(provenance.get("source"), str) or not provenance["source"].strip():
            errors.append(f"{label}: provenance.source must be a nonempty string")
        if not isinstance(provenance, dict) or not isinstance(provenance.get("source_sha256"), str) or not SHA256.fullmatch(provenance["source_sha256"]):
            errors.append(f"{label}: provenance.source_sha256 must be a SHA-256 digest")


def _source_id_errors(collections: dict[str, object], ids: set[str], errors: list[str]) -> None:
    """Resolve source IDs against delivered assets and historical metadata."""
    for collection, records in collections.items():
        if not isinstance(records, list):
            continue
        for index, record in enumerate(records):
            if not isinstance(record, dict) or not isinstance(record.get("provenance"), dict):
                continue
            provenance = record["provenance"]
            if "source_ids" not in provenance:
                continue
            source_ids = provenance["source_ids"]
            label = f"{collection}[{index}]"
            if not isinstance(source_ids, list):
                errors.append(f"{label}: provenance.source_ids must be a list")
                continue
            for identifier in source_ids:
                if not isinstance(identifier, str) or not identifier.strip():
                    errors.append(f"{label}: source id must be a nonempty string")
                elif identifier not in ids:
                    errors.append(f"{label}: unknown source id {identifier!r}")


def validate(root: Path) -> dict[str, object]:
    errors: list[str] = []
    checked = 0
    root = root.resolve()
    manifest_path, path_error = _safe_path(root, "assets/manifest.json")
    if path_error:
        return {"valid": False, "checked": 0, "errors": [f"assets/manifest.json: {path_error}"]}
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"),
                              object_pairs_hook=_unique_json_object,
                              parse_constant=_invalid_json_constant)
    except (OSError, UnicodeError, ValueError) as error:
        return {"valid": False, "checked": 0, "errors": [f"assets/manifest.json: cannot read valid JSON ({error})"]}
    if not isinstance(manifest, dict) or not _integer(manifest.get("schema_version")) or manifest["schema_version"] != 1:
        return {"valid": False, "checked": 0, "errors": ["assets/manifest.json: schema_version must be 1"]}
    assets = manifest.get("assets")
    if not isinstance(assets, list):
        return {"valid": False, "checked": 0, "errors": ["assets/manifest.json: assets must be a list"]}
    ids: set[str] = set()
    paths: set[str] = set()
    for index, asset in enumerate(assets):
        label = f"assets[{index}]"
        if not isinstance(asset, dict):
            errors.append(f"{label}: asset must be an object")
            continue
        identifier = asset.get("id")
        if not isinstance(identifier, str) or not identifier.strip():
            errors.append(f"{label}: id must be a nonempty string")
        elif identifier in ids:
            errors.append(f"{label}: duplicate id {identifier!r}")
        else:
            ids.add(identifier)
        relative = asset.get("path")
        path, path_error = _safe_path(root, relative)
        if path_error:
            errors.append(f"{label}: {path_error}")
            continue
        label = relative
        if relative in paths:
            errors.append(f"{label}: duplicate path")
        paths.add(relative)
        if not isinstance(asset.get("category"), str) or not asset["category"].strip():
            errors.append(f"{label}: category must be a nonempty string")
        for field in ("role", "rights"):
            if not isinstance(asset.get(field), str) or not asset[field].strip():
                errors.append(f"{label}: {field} must be a nonempty string")
        if not isinstance(asset.get("status"), str) or asset["status"] not in STATUSES:
            errors.append(f"{label}: invalid status")
        provenance = asset.get("provenance")
        if not isinstance(provenance, dict) or not isinstance(provenance.get("source"), str) or not provenance["source"].strip():
            errors.append(f"{label}: provenance.source must be a nonempty string")
        if not isinstance(provenance, dict) or not isinstance(provenance.get("source_sha256"), str) or not SHA256.fullmatch(provenance["source_sha256"]):
            errors.append(f"{label}: provenance.source_sha256 must be a SHA-256 digest")
        digest = asset.get("sha256")
        if not isinstance(digest, str) or not SHA256.fullmatch(digest):
            errors.append(f"{label}: sha256 must be a SHA-256 digest")
        size = asset.get("bytes")
        if not _integer(size) or size < 0:
            errors.append(f"{label}: bytes must be a nonnegative integer")
        expected_format = ASSET_FORMATS.get(path.suffix.lower())
        declared_format = asset.get("format")
        normalized_format = declared_format.lower() if isinstance(declared_format, str) else None
        if normalized_format == "jpg":
            normalized_format = "jpeg"
        if expected_format is None or normalized_format != expected_format:
            errors.append(f"{label}: format must match a supported asset extension")
        if expected_format in set(IMAGE_FORMATS.values()) - {"svg"}:
            for field in ("width", "height"):
                if not _integer(asset.get(field)) or asset[field] <= 0:
                    errors.append(f"{label}: {field} must be a positive integer")
            if not isinstance(asset.get("mode"), str) or not asset["mode"].strip():
                errors.append(f"{label}: mode must be a nonempty string")
            if not isinstance(asset.get("has_alpha"), bool):
                errors.append(f"{label}: has_alpha must be a boolean")
        if expected_format == "svg" and not isinstance(asset.get("embedded_raster"), bool):
            errors.append(f"{label}: embedded_raster must be a boolean")
        if not path.is_file():
            errors.append(f"{label}: file is missing or is not a regular file")
            continue
        try:
            data = path.read_bytes()
        except OSError as error:
            errors.append(f"{label}: cannot read file ({error.strerror})")
            continue
        checked += 1
        if size != len(data):
            errors.append(f"{label}: byte count mismatch (manifest={size}, actual={len(data)})")
        actual_digest = hashlib.sha256(data).hexdigest()
        if not isinstance(digest, str) or digest.lower() != actual_digest:
            errors.append(f"{label}: SHA-256 mismatch")
        if isinstance(provenance, dict) and provenance.get("source") == f"repository-authored:{relative}":
            # This URI names the current delivered file. Historical revisions
            # need an explicit revision source, not a commit claimed alongside
            # an unqualified pointer to the current file.
            source_digest = provenance.get("source_sha256")
            if not isinstance(source_digest, str) or source_digest.lower() != actual_digest:
                errors.append(f"{label}: repository-authored self-source SHA-256 must match the delivered bytes")
        try:
            if expected_format is not None and expected_format not in VALIDATED_FORMATS:
                raise ValueError(f"active format {expected_format} has no decoder; use a validated PNG export or archived_sources metadata")
            if expected_format == "png":
                metadata = _png_metadata(data)
                for key, actual in metadata.items():
                    if key in asset:
                        declared = asset[key]
                        correct_type = isinstance(declared, bool) if key == "has_alpha" else _integer(declared) if key in {"width", "height"} else isinstance(declared, str)
                        if not correct_type or declared != actual:
                            errors.append(f"{label}: {key} mismatch (manifest={declared!r}, actual={actual!r})")
            elif expected_format == "svg":
                metadata = _svg_metadata(data, asset.get("status"))
                declared = asset.get("embedded_raster")
                if not isinstance(declared, bool) or declared != metadata["embedded_raster"]:
                    errors.append(f"{label}: embedded_raster must match the SVG contents")
                if metadata["embedded_raster"] and asset.get("status") != "legacy":
                    errors.append(f"{label}: embedded raster SVG wrappers must have legacy status")
            elif expected_format == "css":
                _css_sidecar(data, root, relative)
            elif expected_format == "json":
                _json_sidecar(data)
        except ValueError as error:
            errors.append(f"{label}: {error}")
    archived_sources = manifest.get("archived_sources", [])
    _archived_source_errors(archived_sources, ids, errors)
    _source_id_errors({"assets": assets, "archived_sources": archived_sources}, ids, errors)
    for relative in sorted(_managed_images(root, errors) - paths):
        errors.append(f"{relative}: managed image has no manifest entry")
    return {"valid": not errors, "checked": checked, "errors": errors}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1], help="repository root (defaults to this script's repository)")
    arguments = parser.parse_args()
    result = validate(arguments.root)
    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0 if result["valid"] else 1


if __name__ == "__main__":
    sys.exit(main())
