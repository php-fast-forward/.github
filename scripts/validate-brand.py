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
SHA256 = re.compile(r"[0-9a-fA-F]{64}\Z")
PNG_SIGNATURE = b"\x89PNG\r\n\x1a\n"
LEGACY_SVG_DOCTYPE = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
RASTER_MIMES = {
    "image/png", "image/jpeg", "image/jpg", "image/gif", "image/webp",
    "image/bmp", "image/tiff", "image/x-icon", "image/vnd.microsoft.icon",
}


def _integer(value: object) -> bool:
    return isinstance(value, int) and not isinstance(value, bool)


def _safe_path(root: Path, value: object) -> tuple[Path | None, str | None]:
    """Reject aliases and symlinks before reading any manifest-controlled file."""
    if not isinstance(value, str) or not value or "\\" in value:
        return None, "path must be a nonempty repository-relative POSIX path"
    if any(ord(character) < 32 for character in value):
        return None, "path contains a control character"
    relative = PurePosixPath(value)
    if relative.is_absolute() or any(part in {"", ".", ".."} for part in value.split("/")):
        return None, "path must not be absolute or contain empty, . or .. components"
    if ":" in relative.parts[0]:
        return None, "path must not use an absolute drive or URL prefix"
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


def _png_metadata(data: bytes) -> dict[str, object]:
    if not data.startswith(PNG_SIGNATURE):
        raise ValueError("invalid PNG signature")
    position = len(PNG_SIGNATURE)
    header = None
    transparency = False
    seen_image_data = False
    seen_end = False
    while position < len(data):
        if len(data) - position < 12:
            raise ValueError("truncated PNG chunk")
        length = struct.unpack_from(">I", data, position)[0]
        kind = data[position + 4:position + 8]
        end = position + 12 + length
        if end > len(data):
            raise ValueError("truncated PNG chunk payload")
        payload = data[position + 8:position + 8 + length]
        checksum = struct.unpack_from(">I", data, position + 8 + length)[0]
        if zlib.crc32(kind + payload) & 0xFFFFFFFF != checksum:
            raise ValueError("invalid PNG chunk checksum")
        if header is None and kind != b"IHDR":
            raise ValueError("PNG must begin with IHDR")
        if kind == b"IHDR":
            if header is not None or length != 13:
                raise ValueError("invalid PNG IHDR")
            header = struct.unpack(">IIBBBBB", payload)
        elif kind == b"tRNS":
            transparency = True
        elif kind == b"IDAT":
            seen_image_data = True
        elif kind == b"IEND":
            if length != 0 or end != len(data):
                raise ValueError("invalid PNG IEND or trailing data")
            seen_end = True
            break
        position = end
    if header is None or not seen_image_data or not seen_end:
        raise ValueError("PNG is missing IHDR, IDAT or IEND")
    width, height, depth, color, compression, filtering, interlace = header
    depths = {0: {1, 2, 4, 8, 16}, 2: {8, 16}, 3: {1, 2, 4, 8}, 4: {8, 16}, 6: {8, 16}}
    if not width or not height or depth not in depths.get(color, set()):
        raise ValueError("invalid PNG dimensions, bit depth or color type")
    if compression != 0 or filtering != 0 or interlace not in {0, 1}:
        raise ValueError("unsupported PNG header fields")
    modes = {0: "1" if depth == 1 else "I" if depth == 16 else "L", 2: "RGB", 3: "P", 4: "LA", 6: "RGBA"}
    return {"width": width, "height": height, "mode": modes[color], "has_alpha": color in {4, 6} or transparency}


def _jpeg_metadata(data: bytes) -> dict[str, object]:
    if not data.startswith(b"\xff\xd8"):
        raise ValueError("invalid JPEG signature")
    position = 2
    scanning = False
    metadata = None
    while position < len(data):
        if scanning:
            marker_start = data.find(b"\xff", position)
            if marker_start < 0:
                break
            position = marker_start
        elif data[position] != 0xFF:
            raise ValueError("invalid JPEG marker")
        while position < len(data) and data[position] == 0xFF:
            position += 1
        if position >= len(data):
            break
        marker = data[position]
        position += 1
        if marker == 0 and scanning:
            continue
        if 0xD0 <= marker <= 0xD7 and scanning:
            continue
        if marker == 0xD9:
            if metadata is None:
                raise ValueError("JPEG is missing its frame header")
            return metadata
        if marker in {0, 0xD8} or 0xD0 <= marker <= 0xD7:
            raise ValueError("unexpected JPEG marker")
        if marker == 0x01:
            continue
        scanning = False
        if len(data) - position < 2:
            raise ValueError("truncated JPEG segment")
        length = struct.unpack_from(">H", data, position)[0]
        end = position + length
        if length < 2 or end > len(data):
            raise ValueError("invalid JPEG segment length")
        if marker in {0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF}:
            if length < 8:
                raise ValueError("truncated JPEG frame header")
            height, width, components = struct.unpack_from(">HHB", data, position + 3)
            if not width or not height or components not in {1, 3, 4} or length != 8 + 3 * components:
                raise ValueError("invalid JPEG dimensions or frame components")
            metadata = {"width": width, "height": height, "mode": {1: "L", 3: "RGB", 4: "CMYK"}[components], "has_alpha": False}
        position = end
        if marker == 0xDA:
            scanning = True
    raise ValueError("JPEG is missing its end marker")


def _local_name(name: str) -> str:
    return name.rsplit("}", 1)[-1].lower()


def _check_css(value: str) -> None:
    # CSS escapes can disguise url()/@import. They are unnecessary in these assets.
    if "\\" in value:
        raise ValueError("SVG CSS escapes are not permitted")
    plain = re.sub(r"/\*.*?\*/", "", value, flags=re.DOTALL)
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
            if attribute == "style" or re.search(r"url\s*\(", value, flags=re.IGNORECASE):
                _check_css(value)
        if tag == "image":
            if not image_sources:
                raise ValueError("SVG image is missing its embedded raster source")
            for source in image_sources:
                match = re.fullmatch(r"data:([^;,]+);base64,([\s\S]+)", source, flags=re.IGNORECASE)
                if not match or match.group(1).lower() not in RASTER_MIMES:
                    raise ValueError("SVG images must use embedded base64 raster data")
                try:
                    payload = base64.b64decode("".join(match.group(2).split()), validate=True)
                except (ValueError, binascii.Error) as error:
                    raise ValueError("SVG contains invalid base64 raster data") from error
                if not payload:
                    raise ValueError("SVG contains empty embedded raster data")
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


def _json_sidecar(data: bytes) -> None:
    def invalid_constant(value: str) -> None:
        raise ValueError(f"invalid JSON constant {value}")
    try:
        document = json.loads(data.decode("utf-8-sig"), parse_constant=invalid_constant)
    except (UnicodeError, json.JSONDecodeError, ValueError) as error:
        raise ValueError("JSON sidecar must contain valid UTF-8 JSON") from error
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


def validate(root: Path) -> dict[str, object]:
    errors: list[str] = []
    checked = 0
    root = root.resolve()
    manifest_path, path_error = _safe_path(root, "assets/manifest.json")
    if path_error:
        return {"valid": False, "checked": 0, "errors": [f"assets/manifest.json: {path_error}"]}
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        return {"valid": False, "checked": 0, "errors": [f"assets/manifest.json: cannot read valid JSON ({type(error).__name__})"]}
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
        if not isinstance(digest, str) or digest.lower() != hashlib.sha256(data).hexdigest():
            errors.append(f"{label}: SHA-256 mismatch")
        try:
            if expected_format in {"png", "jpeg"}:
                metadata = _png_metadata(data) if expected_format == "png" else _jpeg_metadata(data)
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
