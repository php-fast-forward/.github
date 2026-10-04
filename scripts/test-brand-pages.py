#!/usr/bin/env python3
"""Exercise Pages boundaries with isolated synthetic repositories."""

from __future__ import annotations

import hashlib
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location("brand_pages", Path(__file__).with_name("build-brand-pages.py"))
PAGES = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PAGES)
REPOSITORY_URL = "https://github.com/php-fast-forward/.github"


class PagesTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name) / "repository"
        self.output = Path(self.temporary.name) / "site"
        self.root.mkdir()
        self.rows = []
        self.write("assets/README.md", "# Assets\n\n[Manifest](manifest.json)\n")
        self.write("DESIGN.md", "# Design\n")
        self.write("docs/brand/index.html", '<!doctype html><html><body id="main"><img src="../../assets/current.png" alt="Current"><a href="../../DESIGN.md">Design</a><a href="../../assets/manifest.json">Catalog</a></body></html>')
        self.asset("assets/current.png", "canonical")
        self.save_manifest()

    def write(self, relative, content):
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)
        return path

    def asset(self, relative, status):
        path = self.write(relative, "synthetic public fixture")
        self.rows.append({"id": relative, "path": relative, "status": status, "sha256": hashlib.sha256(path.read_bytes()).hexdigest()})

    def save_manifest(self):
        self.write("assets/manifest.json", json.dumps({"schema_version": 1, "description": "Synthetic inventory.", "assets": self.rows}))

    def build(self):
        return PAGES.build(self.root, self.output, REPOSITORY_URL, "main")

    def test_only_approved_files_and_active_profile_applications_are_published(self):
        self.asset("assets/legacy.png", "legacy")
        self.asset("references/source.png", "reference")
        self.asset("assets/study.png", "exploratory")
        self.asset("assets/styles/navigation.css", "reference")
        self.asset("profile/assets/brand-hero-banner.png", "canonical")
        self.asset("profile/assets/docs-installation.png", "canonical")
        self.write("profile/assets/github-social-preview.jpg", "protected untracked fixture")
        self.write("assets/untracked-photo.jpg", "untracked fixture")
        self.write("backup/source.png", "archive fixture")
        self.write("private/photo.jpg", "private fixture")
        self.write("assets/brand/source/OFL.txt", "Public font license fixture")
        self.write("assets/brand/source/font.ttf", "Public font fixture")
        self.write("scripts/build-brand-logos.py", "# Public logo builder fixture\n")
        self.save_manifest()
        count = self.build()
        self.assertEqual(count, sum(1 for path in self.output.rglob("*") if path.is_file()))
        for relative in ("references/source.png", "assets/legacy.png", "assets/untracked-photo.jpg", "profile/assets/github-social-preview.jpg", "backup/source.png", "private/photo.jpg"):
            self.assertFalse((self.output / relative).exists(), relative)
        for relative in ("assets/current.png", "assets/study.png", "assets/styles/navigation.css", "profile/assets/brand-hero-banner.png", "profile/assets/docs-installation.png", "assets/brand/source/OFL.txt", "assets/brand/source/font.ttf", "scripts/build-brand-logos.py"):
            self.assertTrue((self.output / relative).is_file(), relative)
        published = json.loads((self.output / "assets/manifest.json").read_text())
        self.assertNotIn("references/source.png", [row["path"] for row in published["assets"]])
        self.assertIn('url=docs/brand/index.html', (self.output / "index.html").read_text())

    def test_reference_images_become_explicit_links_and_public_paths_stay_relative(self):
        self.write("references/source.png", "reference fixture")
        self.write("docs/brand/index.html", '<html><body id="main"><img src="../../references/source.png" alt="Package reference"><a href="../../references/source.png">Original</a><a href="../../DESIGN.md">Design</a><a href="../../assets/manifest.json">Catalog</a></body></html>')
        self.build()
        page = (self.output / "docs/brand/index.html").read_text()
        self.assertNotIn("<img", page)
        self.assertIn("View reference in the repository: Package reference", page)
        self.assertIn(f"{REPOSITORY_URL}/blob/main/references/source.png", page)
        self.assertIn('href="../../DESIGN.md"', page)
        self.assertIn('href="../../assets/manifest.json"', page)

    def test_filtered_provenance_preserves_external_ids_and_closes_local_registry(self):
        self.asset("references/source.png", "reference")
        self.asset("assets/legacy.png", "legacy")
        self.asset("assets/derived.png", "canonical")
        derived = self.rows[-1]
        derived["rights"] = {"status": "unconfirmed", "note": "Synthetic rights fixture"}
        derived["provenance"] = {
            "source_ids": ["assets/current.png", "references/source.png", "archived-origin", "assets/legacy.png"],
            "repository_source_ids": ["already-external"],
            "raw_source_sha256": "a" * 64,
            "created_at": "2026-10-04",
        }
        self.save_manifest()
        source = json.loads((self.root / "assets/manifest.json").read_text())
        source["archived_sources"] = [{
            "id": "archived-origin", "path": "backup/origin.png",
            "provenance": {"source_ids": ["references/source.png"], "raw_source_sha256": "b" * 64},
        }]
        self.write("assets/manifest.json", json.dumps(source))
        original = (self.root / "assets/manifest.json").read_bytes()
        ref = "0123456789abcdef0123456789abcdef01234567"
        PAGES.build(self.root, self.output, REPOSITORY_URL, ref)
        published = json.loads((self.output / "assets/manifest.json").read_text())
        row = next(row for row in published["assets"] if row["id"] == "assets/derived.png")
        provenance = row["provenance"]
        self.assertEqual(provenance["source_ids"], ["assets/current.png", "archived-origin"])
        self.assertEqual(provenance["repository_source_ids"], ["already-external", "references/source.png", "assets/legacy.png"])
        self.assertEqual(provenance["repository_manifest"], f"{REPOSITORY_URL}/blob/{ref}/assets/manifest.json")
        self.assertEqual(provenance["raw_source_sha256"], derived["provenance"]["raw_source_sha256"])
        self.assertEqual(provenance["created_at"], derived["provenance"]["created_at"])
        self.assertEqual(row["rights"], derived["rights"])
        self.assertEqual(row["sha256"], derived["sha256"])
        registry = published["assets"] + published["archived_sources"]
        available_ids = {row["id"] for row in registry}
        for row in registry:
            self.assertLessEqual(set(row.get("provenance", {}).get("source_ids", [])), available_ids)
        archived = published["archived_sources"][0]["provenance"]
        self.assertEqual(archived["source_ids"], [])
        self.assertEqual(archived["repository_source_ids"], ["references/source.png"])
        self.assertEqual(archived["repository_manifest"], provenance["repository_manifest"])
        self.assertEqual(archived["raw_source_sha256"], "b" * 64)
        self.assertEqual((self.root / "assets/manifest.json").read_bytes(), original)
        self.assertFalse((self.output / "references").exists())
        self.assertFalse((self.output / "backup").exists())

    def test_missing_resource_blocks_build_before_any_output(self):
        self.write("docs/brand/index.html", '<html><img src="../../assets/missing.png" alt="Missing"></html>')
        with self.assertRaisesRegex(PAGES.BuildError, "Unpublished resource"):
            self.build()
        self.assertFalse(self.output.exists())

    def test_hash_mismatch_blocks_build(self):
        self.write("assets/current.png", "changed fixture")
        with self.assertRaisesRegex(PAGES.BuildError, "changed after cataloging"):
            self.build()
        self.assertFalse(self.output.exists())

    def test_symlink_cannot_publish_external_content(self):
        source = self.root / "assets/current.png"
        source.unlink()
        outside = Path(self.temporary.name) / "external.txt"
        outside.write_text("external fixture")
        source.symlink_to(outside)
        with self.assertRaisesRegex(PAGES.BuildError, "Symlinks"):
            self.build()

    def test_manifest_traversal_is_rejected(self):
        self.rows[0]["path"] = "assets/../private/photo.jpg"
        self.save_manifest()
        with self.assertRaisesRegex(PAGES.BuildError, "Unsafe path"):
            self.build()

    def test_private_asset_path_is_rejected(self):
        self.asset("assets/private/photo.png", "canonical")
        self.save_manifest()
        with self.assertRaisesRegex(PAGES.BuildError, "Private/archive"):
            self.build()

    def test_hidden_source_files_cannot_enter_the_artifact(self):
        self.asset("assets/.hidden.png", "canonical")
        self.save_manifest()
        with self.assertRaisesRegex(PAGES.BuildError, "Hidden source"):
            self.build()

    def test_existing_output_and_checkout_directories_are_never_overwritten(self):
        self.output.mkdir()
        marker = self.output / "keep.txt"
        marker.write_text("keep fixture")
        with self.assertRaisesRegex(PAGES.BuildError, "nonempty"):
            self.build()
        self.assertEqual(marker.read_text(), "keep fixture")
        with self.assertRaisesRegex(PAGES.BuildError, "only the _site"):
            PAGES.build(self.root, self.root / "assets/empty", REPOSITORY_URL, "main")

    def test_broken_html_anchor_blocks_upload(self):
        self.write("docs/brand/index.html", '<html><body id="main"><a href="#missing">Missing</a></body></html>')
        with self.assertRaisesRegex(PAGES.BuildError, "Missing anchor"):
            self.build()


if __name__ == "__main__":
    unittest.main()
