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

    def test_awaiting_review_artwork_requires_explicit_gallery_authorization(self):
        self.asset("assets/draft.png", "exploratory")
        self.rows[-1]["review_status"] = "awaiting-review"
        self.asset("assets/gallery.png", "exploratory")
        self.rows[-1].update({
            "review_status": "awaiting-review",
            "publication": {"scope": "brand-review-gallery", "authorization": "maintainer-request",
                            "recorded_at": "2026-10-05", "evidence": "Maintainer requested the gallery.",
                            "canonical_selection": False},
        })
        self.asset("assets/unconfirmed.png", "exploratory")
        self.rows[-1].update({
            "review_status": "awaiting-review",
            "publication": {"scope": "brand-review-gallery"},
        })
        self.asset("assets/string-authorization.png", "exploratory")
        self.rows[-1].update({"review_status": "awaiting-review", "publication": "local draft"})
        self.save_manifest()
        self.build()
        self.assertFalse((self.output / "assets/draft.png").exists())
        self.assertFalse((self.output / "assets/unconfirmed.png").exists())
        self.assertFalse((self.output / "assets/string-authorization.png").exists())
        self.assertTrue((self.output / "assets/gallery.png").is_file())
        rows = json.loads((self.output / "assets/manifest.json").read_text())["assets"]
        gallery = next(row for row in rows if row["path"] == "assets/gallery.png")
        self.assertEqual(gallery["status"], "exploratory")
        self.assertEqual(gallery["review_status"], "awaiting-review")

    def test_no_publication_state_is_excluded_even_for_canonical_assets(self):
        self.asset("assets/private-preview.png", "canonical")
        self.rows[-1]["publication"] = "local-not-published"
        self.asset("assets/approved-logo.png", "canonical")
        authorization = {"status": "authorized", "scope": "brand-public-library",
                         "authorization": "maintainer-request", "initial_status": "local-not-published",
                         "recorded_at": "2026-10-05", "evidence": "Maintainer requested publication."}
        self.rows[-1]["publication"] = authorization
        self.save_manifest()
        self.build()
        self.assertFalse((self.output / "assets/private-preview.png").exists())
        self.assertTrue((self.output / "assets/approved-logo.png").is_file())
        rows = json.loads((self.output / "assets/manifest.json").read_text())["assets"]
        published = next(row for row in rows if row["path"] == "assets/approved-logo.png")
        self.assertEqual(published["publication"], authorization)

    def test_unknown_publication_metadata_is_excluded_for_all_active_statuses(self):
        record = {"status": "authorized", "scope": "brand-public-library",
                  "authorization": "maintainer-request", "recorded_at": "2026-10-05",
                  "evidence": "Maintainer requested publication."}
        invalid = ["not-published", "authorized", None, [], {},
                   {**record, "scope": "unknown"}, {**record, "status": "denied"},
                   {**record, "authorization": "unknown"}, {**record, "evidence": ""},
                   {**record, "recorded_at": None},
                   {"scope": "brand-review-gallery", "authorization": "maintainer-request"}]
        paths = []
        for status in ("canonical", "package-variant", "reference", "exploratory"):
            for index, publication in enumerate(invalid):
                relative = f"assets/{status}-{index}.png"
                self.asset(relative, status)
                self.rows[-1]["publication"] = publication
                paths.append(relative)
        self.save_manifest()
        self.build()
        for relative in paths:
            self.assertFalse((self.output / relative).exists(), relative)
        rows = json.loads((self.output / "assets/manifest.json").read_text())["assets"]
        self.assertEqual([row["path"] for row in rows], ["assets/current.png"])

    def test_gallery_authorization_cannot_publish_canonical_or_selected_artwork(self):
        record = {"scope": "brand-review-gallery", "authorization": "maintainer-request",
                  "recorded_at": "2026-10-05", "evidence": "Maintainer requested the gallery.",
                  "canonical_selection": False}
        self.asset("assets/canonical-with-gallery.png", "canonical")
        self.rows[-1]["publication"] = record
        self.asset("assets/selected-gallery.png", "exploratory")
        self.rows[-1].update({"review_status": "awaiting-review",
                              "publication": {**record, "canonical_selection": True}})
        self.save_manifest()
        self.build()
        self.assertFalse((self.output / "assets/canonical-with-gallery.png").exists())
        self.assertFalse((self.output / "assets/selected-gallery.png").exists())

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

    def test_external_resources_are_rejected_but_navigation_is_preserved(self):
        for element in (
            '<img src="https://example.invalid/art.png">',
            '<img src=//example.invalid/art.png>',
            '<source srcset="../../assets/current.png 1x, https://example.invalid/art.png 2x">',
            '<script src="https://example.invalid/code.js"></script>',
            '<link rel="stylesheet" href="https://example.invalid/style.css">',
            '<video poster="https://example.invalid/poster.png"></video>',
            '<object data="https://example.invalid/content.svg"></object>',
            '<img src="data:image/png;base64,aGVsbG8=">',
            '<div style="background: url(https://example.invalid/art.png)"></div>',
            '<style>body { background: url(https://example.invalid/art.png); }</style>',
            '<svg><image xlink:href="https://example.invalid/art.png" /></svg>',
        ):
            with self.subTest(element=element):
                self.write("docs/brand/index.html", f"<html><body>{element}</body></html>")
                with self.assertRaisesRegex(PAGES.BuildError, "External resource"):
                    self.build()
                self.assertFalse(self.output.exists())
        self.write("docs/brand/index.html", '<html><body><a href="https://example.invalid/guide">External guide</a></body></html>')
        self.build()
        self.assertIn('href="https://example.invalid/guide"', (self.output / "docs/brand/index.html").read_text())

    def test_stylesheets_must_be_selected_resources(self):
        self.write("references/unpublished.css", "body { color: red; }")
        for element in (
            '<link rel="stylesheet" href="../../references/unpublished.css">',
            '<link rel=stylesheet href=../../references/unpublished.css>',
            '<link rel="stylesheet" href="../../assets/missing.css" />',
        ):
            with self.subTest(element=element):
                self.write("docs/brand/index.html", f"<html><head>{element}</head></html>")
                with self.assertRaisesRegex(PAGES.BuildError, "Unpublished resource"):
                    self.build()
                self.assertFalse(self.output.exists())
        self.asset("assets/styles/current.css", "canonical")
        self.save_manifest()
        self.write("docs/brand/index.html", '<html><head><link rel="stylesheet" href="../../assets/styles/current.css"></head></html>')
        self.build()
        self.assertIn('href="../../assets/styles/current.css"', (self.output / "docs/brand/index.html").read_text())

    def test_output_check_rejects_external_resources(self):
        self.build()
        page = self.output / "docs/brand/index.html"
        for element in (
            '<img src="https://example.invalid/art.png">',
            '<link rel="stylesheet" href="https://example.invalid/style.css">',
            '<source srcset="https://example.invalid/art.png 1x" />',
            '<svg><image xlink:href="https://example.invalid/art.png" /></svg>',
            '<div style="background: url(https://example.invalid/art.png)"></div>',
            '<style>body { background: url(https://example.invalid/art.png); }</style>',
        ):
            with self.subTest(element=element):
                page.write_text(f"<html>{element}</html>")
                with self.assertRaisesRegex(PAGES.BuildError, "External resource"):
                    PAGES.check_output(self.output)

    def test_nested_documents_and_alternate_bases_are_not_supported(self):
        for element in (
            '<iframe srcdoc="&lt;img src=&quot;https://example.invalid/art.png&quot;&gt;"></iframe>',
            '<base href="https://example.invalid/">',
            '<svg xml:base="https://example.invalid/"><image href="art.png" /></svg>',
        ):
            with self.subTest(element=element):
                self.write("docs/brand/index.html", f"<html>{element}</html>")
                with self.assertRaisesRegex(PAGES.BuildError, "Embedded documents and alternate bases"):
                    self.build()
                self.assertFalse(self.output.exists())
                self.output.mkdir(parents=True)
                (self.output / "index.html").write_text(f"<html>{element}</html>")
                with self.assertRaisesRegex(PAGES.BuildError, "Embedded documents and alternate bases"):
                    PAGES.check_output(self.output)
                (self.output / "index.html").unlink()
                self.output.rmdir()

    def test_output_check_rejects_external_css_urls(self):
        self.build()
        stylesheet = self.output / "assets/external.css"
        stylesheet.write_text('body { background-image: url("https://example.invalid/art.png"); }')
        with self.assertRaisesRegex(PAGES.BuildError, "External resource"):
            PAGES.check_output(self.output)

    def test_css_dependencies_fail_before_output_is_written(self):
        for css, error in (
            ('body { background: url(https://example.invalid/art.png); }', "External resource"),
            ('body { background: url(../../references/art.png); }', "Unpublished resource"),
            ('@import "https://example.invalid/style.css";', "CSS imports"),
            ('body { background: image-set("https://example.invalid/art.png" 1x); }', "image-set"),
        ):
            with self.subTest(css=css):
                self.rows = [row for row in self.rows if row["path"] != "assets/styles/current.css"]
                self.asset("assets/styles/current.css", "canonical")
                path = self.write("assets/styles/current.css", css)
                self.rows[-1]["sha256"] = hashlib.sha256(path.read_bytes()).hexdigest()
                self.save_manifest()
                with self.assertRaisesRegex(PAGES.BuildError, error):
                    self.build()
                self.assertFalse(self.output.exists())
        path = self.write("assets/styles/current.css", 'body { background: url(../current.png); }')
        self.rows[-1]["sha256"] = hashlib.sha256(path.read_bytes()).hexdigest()
        self.save_manifest()
        self.build()
        self.assertTrue((self.output / "assets/styles/current.css").is_file())

    def test_markdown_badges_become_text_links_and_fences_stay_literal(self):
        badge = '<a href="https://example.invalid/repository"><img src="https://example.invalid/badge.svg" alt="Framework repository"></a>'
        snippet = '```html\n<link rel="stylesheet" href="https://example.invalid/style.css">\n```'
        autolink = '<https://example.invalid/Guide>'
        self.write("README.md", f'# Readme\n\n{badge}\n\n![Remote diagram](https://example.invalid/diagram.png)\n\n{autolink}\n\n{snippet}\n')
        self.build()
        source = (self.output / "README.md").read_text()
        self.assertIn('<a href="https://example.invalid/repository">Framework repository</a>', source)
        self.assertIn('[View external image: Remote diagram](https://example.invalid/diagram.png)', source)
        self.assertIn(snippet, source)
        self.assertIn(autolink, source)
        self.assertNotIn('<img', source)

    def test_literal_script_content_is_not_rewritten_as_html(self):
        literal = 'const example = \'<img src="https://example.invalid/art.png">\';'
        self.write("docs/brand/index.html", f'<html><script>{literal}</script></html>')
        self.build()
        self.assertIn(literal, (self.output / "docs/brand/index.html").read_text())

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

    def test_invalid_uncataloged_receipt_blocks_build_before_writing(self):
        self.write("docs/brand/dash-generation.json", "{not valid json")
        with self.assertRaisesRegex(PAGES.BuildError, "Invalid public JSON"):
            self.build()
        self.assertFalse(self.output.exists())

    def test_duplicate_nested_receipt_keys_block_publication(self):
        self.write("docs/brand/dash-generation.json", '{"output":{"sha256":"first","sha256":"second"}}')
        with self.assertRaisesRegex(PAGES.BuildError, "duplicate JSON key"):
            self.build()
        self.assertFalse(self.output.exists())

    def test_nonstandard_constants_and_scalar_receipts_are_rejected(self):
        for payload in ('{"value":NaN}', '{"value":Infinity}', '"scalar"'):
            with self.subTest(payload=payload):
                self.write("docs/brand/dash-generation.json", payload)
                with self.assertRaises(PAGES.BuildError):
                    self.build()
                self.assertFalse(self.output.exists())

    def test_valid_uncataloged_receipt_is_published_unchanged(self):
        payload = '{"output":{"sha256":"recorded"},"references":[{"id":"Dash"}]}'
        self.write("docs/brand/dash-generation.json", payload)
        self.build()
        self.assertEqual((self.output / "docs/brand/dash-generation.json").read_text(), payload)


if __name__ == "__main__":
    unittest.main()
