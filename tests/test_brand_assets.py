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
ARCHIVED_SOURCES_ABSENT = object()


def png_chunk(kind, payload):
    return struct.pack(">I", len(payload)) + kind + payload + struct.pack(">I", zlib.crc32(kind + payload) & 0xFFFFFFFF)


def png_scanlines(width, height, pixels, depth=8, color=6, interlace=0, idat_parts=None, before_idat=(), after_idat=()):
    header = struct.pack(">IIBBBBB", width, height, depth, color, 0, 0, interlace)
    parts = [zlib.compress(pixels)] if idat_parts is None else idat_parts
    return b"\x89PNG\r\n\x1a\n" + png_chunk(b"IHDR", header) + b"".join(before_idat) + b"".join(png_chunk(b"IDAT", part) for part in parts) + b"".join(after_idat) + png_chunk(b"IEND", b"")


def png(width=1, height=1, alpha=True):
    channels = 4 if alpha else 3
    pixels = (b"\x00" + b"\x40\x80\xc0" + (b"\xff" if alpha else b""))
    pixels = (b"\x00" + pixels[1:] * width) * height
    assert len(pixels) == (1 + channels * width) * height
    return png_scanlines(width, height, pixels, color=6 if alpha else 2)


class BrandAssetTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        (self.root / "assets").mkdir()
        self.assets = []
        self.archived_sources = ARCHIVED_SOURCES_ABSENT

    def add_asset(self, relative="assets/mascot.png", content=None, **overrides):
        content = png() if content is None else content
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(content)
        digest = hashlib.sha256(content).hexdigest()
        asset = {
            "id": "mascot", "path": relative, "category": "mascot", "status": "canonical",
            "role": "Isolated brand fixture", "rights": "Original synthetic test fixture",
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

    def add_archived_source(self, **overrides):
        content = png()
        digest = hashlib.sha256(content).hexdigest()
        source = {
            "id": "retired-logo", "path": "assets/retired-logo.png",
            "sha256": digest, "bytes": len(content), "format": "png",
            "provenance": {"source": "isolated historical fixture", "source_sha256": digest},
            "availability": "local-backup-not-distributed",
        }
        source.update(overrides)
        if self.archived_sources is ARCHIVED_SOURCES_ABSENT:
            self.archived_sources = []
        self.archived_sources.append(source)
        return source

    def run_validator(self, expected_code):
        manifest = {"schema_version": 1, "assets": self.assets}
        if self.archived_sources is not ARCHIVED_SOURCES_ABSENT:
            manifest["archived_sources"] = self.archived_sources
        (self.root / "assets" / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
        execution = subprocess.run([sys.executable, "-B", str(VALIDATOR), "--root", str(self.root)], cwd=self.root, capture_output=True, text=True, check=False, timeout=30)
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

    def test_archived_sources_do_not_require_files_or_a_backup(self):
        self.add_archived_source(archive_path="backup/brand/retired/assets/retired-logo.png")
        result = self.run_validator(0)
        self.assertEqual(result, {"valid": True, "checked": 0, "errors": []})
        self.assertFalse((self.root / "backup").exists())
        self.assertFalse((self.root / "assets" / "retired-logo.png").exists())

    def test_historical_path_can_be_reused_by_a_new_active_asset(self):
        self.add_asset("profile/assets/banner.png")
        self.add_archived_source(path="profile/assets/banner.png", sha256="a" * 64, bytes=999)
        result = self.run_validator(0)
        self.assertEqual(result["checked"], 1)

    def test_archived_sources_are_not_read_or_parsed(self):
        self.add_archived_source(path="profile/assets/old-logo.svg", format="svg", sha256="a" * 64, bytes=999)
        path = self.root / "profile" / "assets" / "old-logo.svg"
        path.parent.mkdir(parents=True)
        path.write_bytes(b'<svg><script>invalid retired bytes</script></svg>')
        self.assertEqual(self.run_validator(0)["checked"], 0)

    def test_archive_metadata_does_not_exempt_a_lingering_managed_image(self):
        self.add_archived_source()
        (self.root / "assets" / "retired-logo.png").write_bytes(png())
        self.assert_error(self.run_validator(1), "managed image has no manifest entry")

    def test_archived_source_ids_are_unique_across_both_lists(self):
        self.add_asset()
        self.add_archived_source(id="mascot")
        self.assert_error(self.run_validator(1), "duplicate id")
        self.archived_sources = []
        self.add_archived_source()
        self.add_archived_source(path="profile/assets/old-logo.png")
        self.assert_error(self.run_validator(1), "duplicate id")

    def test_multiple_historical_versions_can_share_a_delivery_path(self):
        self.add_archived_source()
        self.add_archived_source(id="older-logo", sha256="a" * 64)
        self.assertEqual(self.run_validator(0)["checked"], 0)

    def test_archived_source_paths_are_lexically_safe(self):
        for relative in ("../retired.png", "/retired.png", "C:/retired.png", "assets\\retired.png", "assets//retired.png", "./assets/retired.png", "assets/retired\x00.png"):
            with self.subTest(path=relative):
                self.archived_sources = []
                self.add_archived_source(path=relative)
                self.assertTrue(self.run_validator(1)["errors"])

    def test_optional_archive_location_must_be_safe(self):
        self.add_archived_source(archive_path="../outside.png")
        self.assert_error(self.run_validator(1), "archive_path path must not be absolute")

    def test_archived_source_metadata_is_required_and_typed(self):
        cases = (
            ("id", "", "id must be"),
            ("availability", "distributed", "availability must be"),
            ("sha256", "not a digest", "sha256 must be"),
            ("bytes", True, "bytes must be"),
            ("bytes", -1, "bytes must be"),
            ("format", "svg", "format must match"),
            ("provenance", {}, "provenance.source must be"),
            ("provenance", {"source": "fixture", "source_sha256": "bad"}, "provenance.source_sha256 must be"),
        )
        for field, value, message in cases:
            with self.subTest(field=field, value=value):
                self.archived_sources = []
                source = self.add_archived_source()
                source[field] = value
                self.assert_error(self.run_validator(1), message)

    def test_archived_source_list_and_records_must_have_correct_types(self):
        for value in ({"retired-logo": {}}, ["retired-logo"], None):
            with self.subTest(value=value):
                self.archived_sources = value
                self.assertTrue(self.run_validator(1)["errors"])

    def test_source_ids_resolve_active_and_archived_sources_without_io(self):
        self.add_asset()
        active = self.add_asset("assets/new-logo.svg", b'<svg xmlns="http://www.w3.org/2000/svg"/>', id="new-logo")
        self.add_archived_source()
        active["provenance"]["source_ids"] = ["retired-logo"]
        self.archived_sources[0]["provenance"]["source_ids"] = ["mascot"]
        self.assertEqual(self.run_validator(0)["checked"], 2)

    def test_source_ids_reject_unknown_or_malformed_references(self):
        active = self.add_asset()
        for value, message in ((["absent"], "unknown source id"), ("mascot", "source_ids must be a list"), ([{}], "source id must be a nonempty string")):
            with self.subTest(source_ids=value):
                active["provenance"]["source_ids"] = value
                self.assert_error(self.run_validator(1), message)

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

    def test_png_idat_stream_is_validated_with_correct_crc_and_hash(self):
        valid_stream = zlib.compress(b"\x00\x40\x80\xc0\xff")
        for compressed in (b"", b"not a zlib stream", valid_stream[:-1], zlib.compress(b"")):
            with self.subTest(compressed=compressed):
                self.assets = []
                self.add_asset(content=png_scanlines(1, 1, b"", idat_parts=[compressed]))
                self.assert_error(self.run_validator(1), "PNG image data")

    def test_png_scanline_sizes_must_match_the_header(self):
        for pixels in (b"\x00\x40\x80\xc0", b"\x00\x40\x80\xc0\xff\xff"):
            with self.subTest(pixels=pixels):
                self.assets = []
                self.add_asset(content=png_scanlines(1, 1, pixels))
                self.assert_error(self.run_validator(1), "PNG image data")

    def test_png_filter_bytes_are_validated(self):
        self.add_asset(content=png_scanlines(1, 1, b"\x05\x40\x80\xc0\xff"))
        self.assert_error(self.run_validator(1), "invalid scanline filter")

    def test_png_accepts_all_five_filter_methods(self):
        pixels = b"".join(bytes([method]) + b"\x40\x80\xc0\xff" for method in range(5))
        self.add_asset(content=png_scanlines(1, 5, pixels), height=5)
        self.run_validator(0)

    def test_png_zlib_boundaries_can_cross_idat_chunks(self):
        stream = zlib.compress(b"\x00\x40\x80\xc0\xff")
        self.add_asset(content=png_scanlines(1, 1, b"", idat_parts=[b"", stream[:1], stream[1:4], stream[4:-2], stream[-2:], b""]))
        self.run_validator(0)

    def test_png_scanlines_can_cross_bounded_output_blocks(self):
        width = 16384
        pixels = (b"\x01" + b"\x40\x80\xc0\xff" * width) + (b"\x04" + b"\x40\x80\xc0\xff" * width)
        self.add_asset(content=png_scanlines(width, 2, pixels), width=width, height=2)
        self.run_validator(0)

    def test_png_idat_chunks_must_remain_consecutive(self):
        stream = zlib.compress(b"\x00\x40\x80\xc0\xff")
        header = struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)
        content = b"\x89PNG\r\n\x1a\n" + png_chunk(b"IHDR", header) + png_chunk(b"IDAT", stream[:3]) + png_chunk(b"tEXt", b"Comment\x00test") + png_chunk(b"IDAT", stream[3:]) + png_chunk(b"IEND", b"")
        self.add_asset(content=content)
        self.assert_error(self.run_validator(1), "IDAT chunks must be consecutive")

    def test_png_allows_unused_bytes_in_the_final_idat(self):
        stream = zlib.compress(b"\x00\x40\x80\xc0\xff")
        self.add_asset(content=png_scanlines(1, 1, b"", idat_parts=[stream + b"unused trailing bytes"]))
        self.run_validator(0)

    def test_png_adam7_one_pixel_has_no_empty_pass_filters(self):
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x40\x80\xc0\xff", interlace=1))
        self.run_validator(0)

    def test_png_adam7_checks_all_seven_passes(self):
        # The seven 8x8 pass shapes are fixed here independently of the validator.
        pass_shapes = ((1, 1), (1, 1), (2, 1), (2, 2), (4, 2), (4, 4), (8, 4))
        pixels = b"".join((b"\x00" + b"\x40\x80\xc0\xff" * width) * height for width, height in pass_shapes)
        self.add_asset(content=png_scanlines(8, 8, pixels, interlace=1), width=8, height=8)
        self.run_validator(0)
        self.assets = []
        self.add_asset(content=png_scanlines(8, 8, pixels[:-1], interlace=1), width=8, height=8)
        self.assert_error(self.run_validator(1), "incorrect decompressed scanline size")

    def test_png_packed_bits_and_sixteen_bit_channels(self):
        self.add_asset(content=png_scanlines(9, 1, b"\x00\xaa\x80", depth=1, color=0), width=9, mode="1", has_alpha=False)
        self.run_validator(0)
        self.assets = []
        self.add_asset(content=png_scanlines(1, 1, b"\x00" + b"\x40\x00\x80\x00\xc0\x00\xff\xff", depth=16))
        self.run_validator(0)

    def test_indexed_png_requires_palette_before_image_data(self):
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x00", color=3), mode="P", has_alpha=False)
        self.assert_error(self.run_validator(1), "requires PLTE before IDAT")
        self.assets = []
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x00", color=3, before_idat=[png_chunk(b"PLTE", b"\x00\x00\x00")]), mode="P", has_alpha=False)
        self.run_validator(0)

    def test_png_palette_size_and_color_type_are_checked(self):
        for palette, color, depth, message in (
            (b"", 3, 8, "invalid PNG PLTE"),
            (b"\x00\x00", 3, 8, "invalid PNG PLTE"),
            (b"\x00" * 771, 3, 8, "invalid PNG PLTE"),
            (b"\x00" * 9, 3, 1, "exceeds the indexed bit-depth range"),
            (b"\x00" * 3, 0, 8, "invalid PNG PLTE"),
        ):
            with self.subTest(color=color, depth=depth, bytes=len(palette)):
                self.assets = []
                self.add_asset(content=png_scanlines(1, 1, b"\x00\x00", color=color, depth=depth, before_idat=[png_chunk(b"PLTE", palette)]), mode="P" if color == 3 else "L", has_alpha=False)
                self.assert_error(self.run_validator(1), message)

    def test_png_palette_is_unique_and_precedes_image_data(self):
        palette = png_chunk(b"PLTE", b"\x00" * 3)
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x00", color=3, before_idat=[palette, palette]), mode="P", has_alpha=False)
        self.assert_error(self.run_validator(1), "PLTE must appear once before")
        self.assets = []
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x40\x80\xc0\xff", after_idat=[palette]))
        self.assert_error(self.run_validator(1), "PLTE must appear once before")

    def test_indexed_png_reconstructs_all_five_filters_before_checking_indices(self):
        # Each independently encoded row reconstructs the same three indices 1,2,0.
        pixels = b"\x00\x01\x02\x00\x01\x01\x01\xfe\x02\x00\x00\x00\x03\x01\x01\xff\x04\x00\x00\x00"
        self.add_asset(content=png_scanlines(3, 5, pixels, color=3, before_idat=[png_chunk(b"PLTE", b"\x00" * 9)]), width=3, height=5, mode="P", has_alpha=False)
        self.run_validator(0)

    def test_indexed_png_rejects_out_of_range_reconstructed_pixels(self):
        # Both filtered bytes are in range; Sub reconstruction yields indices 2,3.
        self.add_asset(content=png_scanlines(2, 1, b"\x01\x02\x01", color=3, before_idat=[png_chunk(b"PLTE", b"\x00" * 9)]), width=2, mode="P", has_alpha=False)
        self.assert_error(self.run_validator(1), "references a missing palette entry")

    def test_indexed_png_checks_packed_pixels_without_checking_padding_bits(self):
        palette = png_chunk(b"PLTE", b"\x00" * 9)
        # Five 2-bit pixels: 0,1,2,0,1; final six padding bits are arbitrary ones.
        self.add_asset(content=png_scanlines(5, 1, b"\x00\x18\x7f", depth=2, color=3, before_idat=[palette]), width=5, mode="P", has_alpha=False)
        self.run_validator(0)
        self.assets = []
        self.add_asset(content=png_scanlines(5, 1, b"\x00\x18\xff", depth=2, color=3, before_idat=[palette]), width=5, mode="P", has_alpha=False)
        self.assert_error(self.run_validator(1), "references a missing palette entry")

    def test_indexed_png_adam7_resets_the_previous_row_at_each_pass(self):
        pass_shapes = ((1, 1), (1, 1), (2, 1), (2, 2), (4, 2), (4, 4), (8, 4))
        # Up filter: each first row has index 1; later rows contain zero deltas.
        pixels = b"".join((b"\x02" + b"\x01" * width) + (b"\x02" + b"\x00" * width) * (height - 1) for width, height in pass_shapes)
        self.add_asset(content=png_scanlines(8, 8, pixels, color=3, interlace=1, before_idat=[png_chunk(b"PLTE", b"\x00" * 6)]), width=8, height=8, mode="P", has_alpha=False)
        self.run_validator(0)

    def test_png_transparency_matches_palette_and_color_type(self):
        palette = png_chunk(b"PLTE", b"\x00" * 6)
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x00", color=3, before_idat=[palette, png_chunk(b"tRNS", b"\x00")]), mode="P", has_alpha=True)
        self.run_validator(0)
        for color, pixels, chunks, message in (
            (3, b"\x00\x00", [palette, png_chunk(b"tRNS", b"\x00" * 3)], "invalid PNG tRNS palette"),
            (3, b"\x00\x00", [png_chunk(b"tRNS", b"\x00"), palette], "invalid PNG tRNS palette"),
            (6, b"\x00\x40\x80\xc0\xff", [png_chunk(b"tRNS", b"\x00" * 6)], "invalid PNG tRNS for"),
        ):
            with self.subTest(color=color, message=message):
                self.assets = []
                self.add_asset(content=png_scanlines(1, 1, pixels, color=color, before_idat=chunks), mode="P" if color == 3 else "RGBA")
                self.assert_error(self.run_validator(1), message)

    def test_png_unknown_critical_chunks_are_rejected(self):
        self.add_asset(content=png_scanlines(1, 1, b"\x00\x40\x80\xc0\xff", before_idat=[png_chunk(b"ABCD", b"unsupported critical data")]))
        self.assert_error(self.run_validator(1), "unsupported critical chunk")

    def test_raster_formats_without_decoders_fail_even_with_matching_metadata(self):
        for extension, format in (("gif", "gif"), ("webp", "webp"), ("avif", "avif"), ("ico", "ico"), ("bmp", "bmp"), ("tif", "tiff"), ("tiff", "tiff"), ("jpg", "jpeg"), ("jpeg", "jpeg")):
            with self.subTest(format=format, extension=extension):
                self.assets = []
                path = "assets/probe." + extension
                self.add_asset(path, b"not an image", format=format, width=1, height=1, mode="RGB", has_alpha=False)
                self.assert_error(self.run_validator(1), "active format " + format + " has no decoder")
                (self.root / path).unlink()

    def test_jpeg_headers_and_garbage_entropy_do_not_pass_as_valid_pixels(self):
        frame = b"\xff\xc0" + struct.pack(">H", 11) + b"\x08\x00\x01\x00\x01\x01\x01\x11\x00"
        scan = b"\xff\xda" + struct.pack(">H", 8) + b"\x01\x01\x00\x00\x3f\x00"
        content = b"\xff\xd8" + frame + scan + b"not compressed pixels" + b"\xff\xd9"
        self.add_asset("assets/probe.jpg", content, format="jpeg", width=1, height=1, mode="L", has_alpha=False)
        self.assert_error(self.run_validator(1), "active format jpeg has no decoder")

    def test_archived_formats_without_active_decoders_remain_metadata_only(self):
        self.add_archived_source(path="references/historical.jpg", format="jpeg")
        result = self.run_validator(0)
        self.assertEqual(result["checked"], 0)
        self.assertFalse((self.root / "references/historical.jpg").exists())

    def test_repository_authored_self_source_must_match_current_file_bytes(self):
        asset = self.add_asset("assets/style.css", b".fixture { color: #142033; }\n", format="css")
        asset["provenance"] = {"source": "repository-authored:assets/style.css", "source_sha256": "a" * 64}
        self.assert_error(self.run_validator(1), "self-source SHA-256 must match")
        # An arbitrary commit claim does not re-anchor the current-file URI.
        asset["provenance"]["source_commit"] = "b" * 40
        self.assert_error(self.run_validator(1), "self-source SHA-256 must match")
        asset["provenance"]["source_sha256"] = asset["sha256"]
        self.run_validator(0)

    def test_derived_generated_and_archived_sources_can_reference_other_bytes(self):
        asset = self.add_asset()
        for source in ("derived:earlier-master", "generated:isolated-generator"):
            with self.subTest(source=source):
                asset["provenance"] = {"source": source, "source_sha256": "c" * 64}
                self.run_validator(0)
        (self.root / asset["path"]).unlink()
        self.assets = []
        self.add_archived_source(provenance={"source": "repository-authored:assets/retired-logo.png", "source_sha256": "d" * 64})
        result = self.run_validator(0)
        self.assertEqual(result["checked"], 0)

    def test_png_huge_dimensions_fail_before_unbounded_inflation(self):
        self.add_asset(content=png_scanlines(2 ** 30, 1, b"\x00\x40\x80\xc0\xff"), width=2 ** 30)
        self.assert_error(self.run_validator(1), "decoded-size limit")

    def test_png_inflation_cannot_exceed_the_expected_size(self):
        self.add_asset(content=png_scanlines(1, 1, b"\x00" * (1024 * 1024)))
        self.assert_error(self.run_validator(1), "excess decompressed scanline bytes")

    def test_raster_metadata_is_required(self):
        for field in ("width", "height", "mode", "has_alpha"):
            with self.subTest(field=field):
                self.assets = []
                asset = self.add_asset()
                asset.pop(field)
                self.assert_error(self.run_validator(1), f"{field} must be")

    def test_all_assets_require_role_and_rights(self):
        for field in ("role", "rights"):
            with self.subTest(field=field):
                self.assets = []
                asset = self.add_asset()
                asset.pop(field)
                self.assert_error(self.run_validator(1), f"{field} must be a nonempty string")

    def test_svg_embedded_raster_metadata_is_required(self):
        asset = self.add_asset("assets/logo.svg", b'<svg xmlns="http://www.w3.org/2000/svg"/>')
        asset.pop("embedded_raster")
        self.assert_error(self.run_validator(1), "embedded_raster must be a boolean")

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
        self.assert_error(self.run_validator(1), "only image/png is supported")

    def test_svg_embedded_png_pixels_are_validated_beyond_nonempty_base64(self):
        header = struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)
        invalid_stream = b"\x89PNG\r\n\x1a\n" + png_chunk(b"IHDR", header) + png_chunk(b"IDAT", b"not zlib") + png_chunk(b"IEND", b"")
        for payload in (b"not an image", invalid_stream):
            with self.subTest(payload=payload[:16]):
                self.assets = []
                svg = ('<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,' + base64.b64encode(payload).decode("ascii") + '"/></svg>').encode()
                self.add_asset("assets/legacy.svg", svg, status="legacy", embedded_raster=True)
                self.assert_error(self.run_validator(1), "SVG embedded PNG is invalid")

    def test_svg_embedded_raster_mimes_without_decoders_are_rejected(self):
        for mime in ("image/jpeg", "image/gif", "image/webp", "image/bmp"):
            with self.subTest(mime=mime):
                self.assets = []
                svg = ('<svg xmlns="http://www.w3.org/2000/svg"><image href="data:' + mime + ';base64,' + base64.b64encode(b"unvalidated raster bytes").decode("ascii") + '"/></svg>').encode()
                self.add_asset("assets/legacy.svg", svg, status="legacy", embedded_raster=True)
                self.assert_error(self.run_validator(1), "SVG embedded raster format has no decoder")

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

    def test_css_sidecars_reject_image_set_string_sources(self):
        for css in (
            b'.icon {background-image: image-set("https://example.invalid/a.png" 1x)}',
            b'.icon {background-image: -webkit-image-set("https://example.invalid/a.png" 1x)}',
            b'.icon {background-image: IMAGE-SET("./sprite.png" 1x)}',
            b'.icon {background-image: image/**/-set("https://example.invalid/a.png" 1x)}',
        ):
            with self.subTest(css=css):
                self.assets = []
                self.add_asset("references/icons/sprite.css", css, status="reference")
                self.assert_error(self.run_validator(1), "CSS sidecar image-set is not permitted")

    def test_svg_css_rejects_image_set_string_sources(self):
        svg = b'<svg xmlns="http://www.w3.org/2000/svg"><style>.x {fill: image-set("https://example.invalid/a.png" 1x)}</style></svg>'
        self.add_asset("assets/logo.svg", svg)
        self.assert_error(self.run_validator(1), "SVG CSS image-set is not permitted")

    def test_unmanaged_token_exports_do_not_need_inventory_entries(self):
        self.add_asset()
        (self.root / "assets" / "tokens.json").write_text('{"color": "#000000"}', encoding="utf-8")
        (self.root / "assets" / "theme.css").write_text(':root {--color: #000000;}', encoding="utf-8")
        self.run_validator(0)


if __name__ == "__main__":
    unittest.main()
