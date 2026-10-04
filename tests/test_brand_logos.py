"""Verify the delivered vector kit and the public builder in isolated checkouts."""

from collections import Counter
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets/brand/logo-source.json"
VARIANTS = ("color", "dark", "ink", "white")


def output_path(stem, variant):
    suffix = "" if variant == "color" else f"-{variant}"
    return f"assets/brand/fast-forward-{stem}{suffix}.svg"


def tag(element):
    return element.tag.rsplit("}", 1)[-1]


def paths(tree):
    return [element for element in tree.iter() if tag(element) == "path"]


class DeliveredLogoTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = json.loads(SOURCE.read_text())
        cls.exports = {
            (stem, variant): ET.parse(ROOT / output_path(stem, variant)).getroot()
            for stem in ("logo", "mark") for variant in VARIANTS
        }

    def test_variants_share_reviewed_symbol_geometry_and_lettering(self):
        symbol = Counter(part["d"] for part in self.source["symbol"]["paths"])
        reference_letters = None
        for variant in VARIANTS:
            with self.subTest(variant=variant):
                mark = self.exports["mark", variant]
                logo = self.exports["logo", variant]
                self.assertEqual(symbol, Counter(path.attrib["d"] for path in paths(mark)))
                self.assertEqual(mark.attrib["viewBox"], "0 0 320 320")
                symbol_paths = [path for path in paths(logo) if "transform" not in path.attrib]
                self.assertEqual(symbol, Counter(path.attrib["d"] for path in symbol_paths))
                letters = [(path.attrib["d"], path.attrib["transform"]) for path in paths(logo) if "transform" in path.attrib]
                self.assertEqual(len(letters), len("PHPFastForward"))
                if reference_letters is None:
                    reference_letters = letters
                self.assertEqual(reference_letters, letters)
                self.assertEqual(self.exports["logo", "color"].attrib["viewBox"], logo.attrib["viewBox"])

    def test_fast_is_orange_and_forward_uses_the_surface_lettering_color(self):
        for variant in ("color", "dark"):
            with self.subTest(variant=variant):
                letters = [path for path in paths(self.exports["logo", variant]) if "transform" in path.attrib]
                colors = self.source["variants"][variant]
                self.assertEqual([colors["orange"]] * 4, [path.attrib["fill"] for path in letters[3:7]])
                self.assertEqual([colors["lettering"]] * len("PHPForward"), [path.attrib["fill"] for path in letters[:3] + letters[7:]])

    def test_monochrome_visible_paint_has_one_color_and_cream_is_a_knockout(self):
        def visible_fills(element):
            if tag(element) == "defs":
                return []
            values = [element.attrib["fill"]] if "fill" in element.attrib else []
            return values + [value for child in element for value in visible_fills(child)]

        for variant in ("ink", "white"):
            for stem in ("logo", "mark"):
                with self.subTest(stem=stem, variant=variant):
                    tree = self.exports[stem, variant]
                    self.assertEqual({self.source["variants"][variant]["lettering"]}, set(visible_fills(tree)))
                    masks = [element for element in tree.iter() if tag(element) == "mask"]
                    self.assertEqual(len(masks), 1)
                    self.assertEqual(Counter(part["d"] for part in self.source["symbol"]["paths"] if part["role"] == "cream"), Counter(path.attrib["d"] for path in paths(masks[0])))
                    self.assertTrue(any(element.attrib.get("mask") == "url(#fox-knockout)" for element in tree.iter()))

    def test_exports_have_native_paths_and_no_raster_text_or_external_font(self):
        for identity, tree in self.exports.items():
            with self.subTest(export=identity):
                self.assertTrue(paths(tree))
                for element in tree.iter():
                    self.assertNotIn(tag(element).lower(), {"image", "text", "script", "foreignobject", "style"})
                    for name, value in element.attrib.items():
                        self.assertNotIn(name.rsplit("}", 1)[-1].lower(), {"font-family", "font-face", "href"})
                        self.assertNotIn("data:image", value.lower())
                        self.assertNotIn("https:", value.lower())
                        self.assertNotIn("http:", value.lower())

    def test_font_receipt_outlined_source_and_distributed_bytes_agree(self):
        lettering = self.source["lettering"]
        receipt = json.loads((ROOT / "assets/brand/source/font-source.json").read_text())
        self.assertEqual(lettering["font_sha256"], receipt["file"]["sha256"])
        self.assertEqual(lettering["source_commit"], receipt["upstream"]["commit"])
        self.assertEqual(lettering["font_family"], receipt["family"])
        self.assertEqual(lettering["font_style"], receipt["style"])
        self.assertEqual(lettering["authoring"]["instance"], receipt["lettering_instance"])
        catalog = {item["path"]: item["sha256"] for item in lettering["source_files"]}
        for item in (receipt["file"], receipt["license"]):
            data = (ROOT / item["path"]).read_bytes()
            self.assertEqual(item["bytes"], len(data))
            self.assertEqual(item["sha256"], hashlib.sha256(data).hexdigest())
            self.assertEqual(item["sha256"], catalog[item["path"]])
        for relative, digest in catalog.items():
            self.assertEqual(digest, hashlib.sha256((ROOT / relative).read_bytes()).hexdigest())


class IsolatedLogoBuilderTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="brand-logo-test-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        source = json.loads(SOURCE.read_text())
        files = ["scripts/build-brand-logos.py", "assets/brand/logo-source.json"]
        files += [item["path"] for item in source["lettering"]["source_files"]]
        for relative in files:
            target = self.root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / relative, target)

    def run_builder(self, *arguments, expected=0):
        result = subprocess.run(
            [sys.executable, "-B", str(self.root / "scripts/build-brand-logos.py"), *arguments],
            cwd=self.temporary.name, capture_output=True, text=True, timeout=20,
        )
        self.assertEqual(result.returncode, expected, result.stdout + result.stderr)
        return result

    def output_bytes(self):
        return {output_path(stem, variant): (self.root / output_path(stem, variant)).read_bytes()
                for stem in ("logo", "mark") for variant in VARIANTS}

    def test_build_is_deterministic_and_matches_all_delivered_exports(self):
        self.run_builder()
        first = self.output_bytes()
        self.run_builder()
        self.assertEqual(first, self.output_bytes())
        for relative, data in first.items():
            with self.subTest(export=relative):
                self.assertEqual(data, (ROOT / relative).read_bytes())
        result = self.run_builder("--check")
        self.assertIn("Verified 8 SVG logo exports.", result.stdout)
        self.assertEqual(first, self.output_bytes())

    def test_check_rejects_stale_and_missing_exports_without_rewriting(self):
        self.run_builder()
        stale = self.root / output_path("logo", "color")
        stale.write_bytes(stale.read_bytes() + b"<!-- local mutation -->\n")
        before = self.output_bytes()
        result = self.run_builder("--check", expected=1)
        self.assertIn("Stale logo export:", result.stderr)
        self.assertEqual(before, self.output_bytes())
        stale.unlink()
        result = self.run_builder("--check", expected=1)
        self.assertIn("Stale logo export:", result.stderr)
        self.assertFalse(stale.exists())

    def test_font_license_and_receipt_drift_stop_both_check_and_build(self):
        self.run_builder()
        before = self.output_bytes()
        source = json.loads(SOURCE.read_text())
        for item in source["lettering"]["source_files"]:
            path = self.root / item["path"]
            original = path.read_bytes()
            path.write_bytes(original + b"\nfixture drift\n")
            try:
                for arguments in (("--check",), ()):
                    with self.subTest(source=item["path"], arguments=arguments):
                        result = self.run_builder(*arguments, expected=1)
                        self.assertIn("Font source changed: " + item["path"], result.stderr)
                        self.assertEqual(before, self.output_bytes())
            finally:
                path.write_bytes(original)


if __name__ == "__main__":
    unittest.main()
