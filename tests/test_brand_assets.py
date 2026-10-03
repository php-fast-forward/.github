"""Exercise the asset validator's public CLI with isolated repositories."""

import base64
import hashlib
import json
from pathlib import Path
import struct
import subprocess
import sys
import tempfile
import unittest
import zlib


VALIDATOR = Path(__file__).resolve().parents[1] / "scripts" / "validate-brand.py"


def png(width=1, height=1, alpha=True):
    def chunk(kind, payload):
        return struct.pack(">I", len(payload)) + kind + payload + struct.pack(">I", zlib.crc32(kind + payload) & 0xFFFFFFFF)
    channels = 4 if alpha else 3
    header = struct.pack(">IIBBBBB", width, height, 8, 6 if alpha else 2, 0, 0, 0)
    pixels = (b"\x00" + b"\x40\x80\xc0" + (b"\xff" if alpha else b""))
    pixels = (b"\x00" + pixels[1:] * width) * height
    assert len(pixels) == (1 + channels * width) * height
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", header) + chunk(b"IDAT", zlib.compress(pixels)) + chunk(b"IEND", b"")


class BrandAssetTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        (self.root / "assets").mkdir()
        self.assets = []

    def add_asset(self, relative="assets/mascot.png", content=None, **overrides):
        content = png() if content is None else content
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(content)
        digest = hashlib.sha256(content).hexdigest()
        asset = {
            "id": "mascot", "path": relative, "category": "mascot", "status": "canonical",
            "provenance": {"source": "isolated fixture", "source_sha256": digest},
            "sha256": digest, "bytes": len(content), "format": path.suffix[1:],
        }
        if path.suffix == ".png":
            asset.update(width=1, height=1, mode="RGBA", has_alpha=True)
        if path.suffix == ".svg":
            asset["embedded_raster"] = False
        asset.update(overrides)
        self.assets.append(asset)
        return asset

    def run_validator(self, expected_code):
        (self.root / "assets" / "manifest.json").write_text(json.dumps({"schema_version": 1, "assets": self.assets}), encoding="utf-8")
        execution = subprocess.run([sys.executable, "-B", str(VALIDATOR), "--root", str(self.root)], cwd=self.root, capture_output=True, text=True, check=False)
        self.assertEqual(execution.returncode, expected_code, execution.stdout + execution.stderr)
        self.assertEqual(execution.stderr, "")
        result = json.loads(execution.stdout)
        self.assertEqual(result["valid"], expected_code == 0)
        return result

    def assert_error(self, result, text):
        self.assertTrue(any(text in error for error in result["errors"]), result)

    def test_valid_png_and_independent_working_directory(self):
        self.add_asset()
        result = self.run_validator(0)
        self.assertEqual(result, {"valid": True, "checked": 1, "errors": []})

    def test_replaced_file_is_detected_by_digest_and_size(self):
        self.add_asset()
        (self.root / "assets" / "mascot.png").write_bytes(png(width=2))
        result = self.run_validator(1)
        self.assert_error(result, "SHA-256 mismatch")
        self.assert_error(result, "byte count mismatch")

    def test_missing_file(self):
        self.add_asset()
        (self.root / "assets" / "mascot.png").unlink()
        result = self.run_validator(1)
        self.assert_error(result, "file is missing")
        self.assertEqual(result["checked"], 0)

    def test_parent_traversal_is_rejected_before_file_read(self):
        self.add_asset(path="../mascot.png")
        result = self.run_validator(1)
        self.assert_error(result, "path must not be absolute")
        self.assertEqual(result["checked"], 0)

    def test_absolute_path_is_rejected(self):
        self.add_asset(path=str(self.root / "assets" / "mascot.png"))
        self.assert_error(self.run_validator(1), "path must not be absolute")

    def test_symlink_traversal_is_rejected(self):
        self.add_asset()
        target = self.root / "assets" / "mascot.png"
        link = self.root / "assets" / "linked.png"
        link.symlink_to(target)
        self.assets[0]["path"] = "assets/linked.png"
        result = self.run_validator(1)
        self.assert_error(result, "path must not traverse a symlink")
        self.assertEqual(result["checked"], 0)

    def test_duplicate_ids(self):
        self.add_asset()
        self.add_asset("references/mascot.png")
        self.assert_error(self.run_validator(1), "duplicate id")

    def test_duplicate_paths(self):
        asset = self.add_asset()
        self.assets.append(dict(asset, id="second-mascot"))
        self.assert_error(self.run_validator(1), "duplicate path")

    def test_dimensions_are_read_from_png_header(self):
        self.add_asset(width=99)
        self.assert_error(self.run_validator(1), "width mismatch")

    def test_transparency_and_mode_are_verified(self):
        self.add_asset(content=png(alpha=False))
        result = self.run_validator(1)
        self.assert_error(result, "mode mismatch")
        self.assert_error(result, "has_alpha mismatch")

    def test_png_corruption_fails_even_if_manifest_hash_is_updated(self):
        content = bytearray(png())
        content[29] ^= 1
        self.add_asset(content=bytes(content))
        self.assert_error(self.run_validator(1), "invalid PNG chunk checksum")

    def test_unlisted_managed_images_are_detected(self):
        self.add_asset()
        (self.root / "references").mkdir()
        (self.root / "references" / "forgotten.png").write_bytes(png())
        self.assert_error(self.run_validator(1), "references/forgotten.png: managed image has no manifest entry")

    def test_untracked_profile_images_are_outside_managed_inventory(self):
        self.add_asset()
        (self.root / "profile" / "assets").mkdir(parents=True)
        (self.root / "profile" / "assets" / "github-social-preview.jpg").write_bytes(b"untracked preview")
        self.run_validator(0)

    def test_explicit_profile_assets_are_verified(self):
        self.add_asset("profile/assets/logo.png")
        self.run_validator(0)

    def test_safe_vector_svg(self):
        svg = b'<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"/></defs><path fill="url(#g)" d="M0 0L1 1"/></svg>'
        self.add_asset("assets/logo.svg", svg)
        self.run_validator(0)

    def test_unsafe_svg_elements_and_attributes(self):
        for svg in (
            b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            b'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            b'<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.invalid/logo.png"/></svg>',
            b'<svg xmlns="http://www.w3.org/2000/svg"><style>@font-face {src:url(https://example.invalid/font.woff)}</style></svg>',
            b'<!DOCTYPE svg [<!ENTITY external SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&external;</svg>',
            b'<svg xmlns="http://www.w3.org/2000/svg"><image id="i" href="#local"/><set href="#i" attributeName="href" to="https://example.invalid/logo.png"/></svg>',
        ):
            with self.subTest(svg=svg):
                self.assets = []
                self.add_asset("assets/logo.svg", svg)
                result = self.run_validator(1)
                self.assertTrue(result["errors"])

    def test_legacy_embedded_raster_wrapper_is_explicit(self):
        svg = ('<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,' + base64.b64encode(png()).decode("ascii") + '"/></svg>').encode("utf-8")
        self.add_asset("assets/legacy.svg", svg, status="legacy", embedded_raster=True)
        self.run_validator(0)
        self.assets[0]["status"] = "canonical"
        self.assert_error(self.run_validator(1), "embedded raster SVG wrappers must have legacy status")

    def test_nested_svg_data_is_rejected(self):
        svg = b'<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zy8+"/></svg>'
        self.add_asset("assets/legacy.svg", svg, status="legacy", embedded_raster=True)
        self.assert_error(self.run_validator(1), "SVG images must use embedded base64 raster data")

    def test_svg_embedded_classification_matches_contents(self):
        self.add_asset("assets/logo.svg", b'<svg xmlns="http://www.w3.org/2000/svg"/>', embedded_raster=True)
        self.assert_error(self.run_validator(1), "embedded_raster must match")

    def test_invalid_manifest_metadata_is_reported(self):
        self.add_asset(bytes=True, status="approved", provenance={"source": "fixture", "source_sha256": "unknown"})
        result = self.run_validator(1)
        self.assert_error(result, "bytes must be a nonnegative integer")
        self.assert_error(result, "invalid status")
        self.assert_error(result, "provenance.source_sha256")

    def test_invalid_status_type_has_a_json_error(self):
        self.add_asset(status=[])
        self.assert_error(self.run_validator(1), "invalid status")

    def test_utf16_svg_cannot_bypass_entity_restrictions(self):
        content = '<!DOCTYPE svg [<!ENTITY x "expanded">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'.encode("utf-16")
        self.add_asset("assets/logo.svg", content)
        self.assert_error(self.run_validator(1), "SVG must use UTF-8 XML")

    def test_historical_svg_doctype_is_permitted_only_for_legacy(self):
        svg = b'<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg"/>'
        self.add_asset("assets/historical.svg", svg, status="legacy")
        self.run_validator(0)
        self.assets[0]["status"] = "canonical"
        self.assert_error(self.run_validator(1), "SVG DTDs and entities are not permitted")

    def test_historical_doctype_cannot_include_internal_entities(self):
        svg = b'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" [<!ENTITY remote SYSTEM "https://example.invalid/entity">]><svg xmlns="http://www.w3.org/2000/svg">&remote;</svg>'
        self.add_asset("assets/historical.svg", svg, status="legacy")
        self.assert_error(self.run_validator(1), "SVG DTDs and entities are not permitted")

    def test_svg_external_stylesheet_instruction_is_rejected(self):
        svg = b'<?xml-stylesheet type="text/css" href="https://example.invalid/remote.css"?><svg xmlns="http://www.w3.org/2000/svg"/>'
        self.add_asset("assets/logo.svg", svg)
        self.assert_error(self.run_validator(1), "SVG processing instructions are not permitted")

    def test_cataloged_css_and_json_sidecars(self):
        self.add_asset("references/icons/sprite.png")
        self.add_asset("references/icons/sprite.css", b':root {--sprite: url("./sprite.png");}', id="sprite-css", status="reference")
        self.add_asset("references/icons/sprite.json", b'{"icons": [{"name": "docs", "x": 0}]}', id="sprite-json", status="reference")
        self.assertEqual(self.run_validator(0)["checked"], 3)

    def test_corrupt_json_fails_even_when_its_digest_matches(self):
        self.add_asset("references/icons/sprite.json", b'{"icons": [}', status="reference")
        self.assert_error(self.run_validator(1), "JSON sidecar must contain valid UTF-8 JSON")

    def test_json_sidecars_accept_lists_and_reject_scalar_values(self):
        self.add_asset("references/icons/sprite.json", b'["docs", "terminal"]', status="reference")
        self.run_validator(0)
        self.assets = []
        self.add_asset("references/icons/sprite.json", b'"not an inventory"', status="reference")
        self.assert_error(self.run_validator(1), "JSON sidecar must contain an object or list")

    def test_css_sidecars_reject_external_urls_and_traversal(self):
        for css in (
            b'.icon {background: url("https://example.invalid/sprite.png")}',
            b'.icon {background: url("../../../../sprite.png")}',
            b'.icon {background: url("%2e%2e/%2e%2e/%2e%2e/sprite.png")}',
            b'@import "https://example.invalid/style.css";',
        ):
            with self.subTest(css=css):
                self.assets = []
                self.add_asset("references/icons/sprite.css", css, status="reference")
                self.assertTrue(self.run_validator(1)["errors"])

    def test_css_sidecar_dependency_must_exist(self):
        self.add_asset("references/icons/sprite.css", b'.icon {background: url("./missing.png")}', status="reference")
        self.assert_error(self.run_validator(1), "CSS sidecar URL points to a missing file")

    def test_unmanaged_token_exports_do_not_need_inventory_entries(self):
        self.add_asset()
        (self.root / "assets" / "tokens.json").write_text('{"color": "#000000"}', encoding="utf-8")
        (self.root / "assets" / "theme.css").write_text(':root {--color: #000000;}', encoding="utf-8")
        self.run_validator(0)


if __name__ == "__main__":
    unittest.main()
