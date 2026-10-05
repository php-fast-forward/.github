#!/usr/bin/env python3
"""Stage the public brand library for Pages without publishing the checkout.

Usage: python3 scripts/build-brand-pages.py --output-dir /tmp/brand-pages
Run scripts/validate-brand.py first. The destination must be empty; this script
never deletes existing output. Source guides remain Markdown files. References
remain links to GitHub, rather than images fetched by the published pages.
"""

from __future__ import annotations

import argparse
import hashlib
import html
from html.parser import HTMLParser
import json
import posixpath
from pathlib import Path, PurePosixPath
import re
import shutil
from urllib.parse import quote, unquote, urlsplit, urlunsplit


ACTIVE_STATUSES = {"canonical", "package-variant", "exploratory", "reference"}
FORBIDDEN_PARTS = {"backup", "backups", "private", ".git", ".agents", ".codex"}
ROOT_GUIDES = {
    "README.md", "DESIGN.md", "STYLE.md", "SOUL.md", "CONTRIBUTING.md",
    "SUPPORT.md", "SECURITY.md", "CODE_OF_CONDUCT.md", "ACCESSIBILITY.md",
}
PROFILE_APPLICATIONS = {
    "profile/assets/brand-hero-banner.png", "profile/assets/docs-installation.png",
}
TOKEN_FILES = {"tokens.json", "exports.json", "tailwind.theme.json", "theme.css"}
PUBLIC_SOURCE_SUFFIXES = {".ttf", ".txt", ".json", ".md"}
MARKDOWN_LINK = re.compile(r"(!?)\[([^\]]*)\]\(([^\s)]+)([^)]*)\)")
CODE_FENCE = re.compile(r"(```[\s\S]*?```|~~~[\s\S]*?~~~)")


class BuildError(ValueError):
    """The source or output would violate the publication boundary."""


def is_resource(tag: str, attribute: str) -> bool:
    """Separate navigation from content the browser loads into a document."""
    return (attribute in {"src", "srcset", "poster"}
            or (attribute == "data" and tag == "object")
            or (attribute in {"href", "xlink:href"} and tag not in {"a", "area"}))


def check_embedded_document(tag: str, attrs: list[tuple[str, str | None]], owner: str) -> None:
    # Nested documents and alternate bases need a separate publication contract.
    if tag == "base" or any(name in {"srcdoc", "xml:base"} for name, _ in attrs):
        raise BuildError(f"Embedded documents and alternate bases are not supported in {owner}")


def resource_target(owner: str, destination: str) -> tuple[str, object]:
    parsed = urlsplit(html.unescape(destination))
    if parsed.scheme or parsed.netloc:
        raise BuildError(f"External resource in {owner}: {destination}")
    target = local_target(owner, destination)
    # An SVG fragment reference remains inside the selected document.
    if target is None and parsed.fragment:
        return owner, parsed
    if target is None:
        raise BuildError(f"Empty resource in {owner}: {destination}")
    return target


def css_resources(owner: str, css: str) -> list[str]:
    """Use the same supported CSS resource syntax as the asset validator."""
    if "\\" in css:
        raise BuildError(f"CSS escapes are not supported in {owner}")
    plain = re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)
    if re.search(r"@\s*(?:import|font-face)\b|(?:-webkit-)?image-set\s*\(", plain, re.I):
        raise BuildError(f"CSS imports, font-face and image-set are not supported in {owner}")
    return [value.strip().strip("\"'").strip()
            for value in re.findall(r"url\s*\((.*?)\)", plain, re.I | re.DOTALL)]


def safe_file(root: Path, relative: str) -> Path:
    root = root.resolve()
    parts = relative.split("/")
    if not relative or "\\" in relative or PurePosixPath(relative).is_absolute():
        raise BuildError(f"Unsafe path: {relative!r}")
    if any(part in {"", ".", ".."} for part in parts) or ":" in parts[0]:
        raise BuildError(f"Unsafe path: {relative!r}")
    if any(part in FORBIDDEN_PARTS for part in parts):
        raise BuildError(f"Private/archive files are not publishable: {relative}")
    if any(part.startswith(".") for part in parts):
        raise BuildError(f"Hidden source files are not publishable: {relative}")
    current = root
    for part in parts:
        current /= part
        if current.is_symlink():
            raise BuildError(f"Symlinks are not publishable: {relative}")
    if not current.is_file() or root not in current.resolve().parents:
        raise BuildError(f"Missing public file: {relative}")
    return current


def local_target(owner: str, destination: str) -> tuple[str, object] | None:
    parsed = urlsplit(html.unescape(destination))
    if parsed.scheme or parsed.netloc or not parsed.path:
        return None
    decoded = unquote(parsed.path)
    if decoded.startswith("/") or "\\" in decoded:
        raise BuildError(f"Use repository-relative URLs in {owner}: {destination}")
    relative = posixpath.normpath(posixpath.join(posixpath.dirname(owner), decoded))
    if relative == ".." or relative.startswith("../"):
        raise BuildError(f"URL escapes the repository in {owner}: {destination}")
    return relative, parsed


def source_url(root: Path, relative: str, parsed: object, repository_url: str, ref: str) -> str:
    # Outside-site links are public repository sources, never absolute host paths.
    if any(part in FORBIDDEN_PARTS for part in relative.split("/")):
        raise BuildError(f"Private/archive links are not publishable: {relative}")
    source = root / relative
    if source.is_symlink() or not source.exists():
        raise BuildError(f"Missing repository source link: {relative}")
    route = "tree" if source.is_dir() else "blob"
    path = f"{repository_url.rstrip('/')}/{route}/{quote(ref, safe='')}/{quote(relative, safe='/')}"
    return urlunsplit((*urlsplit(path)[:3], parsed.query, parsed.fragment))


def scope_provenance(manifest: dict[str, object], repository_url: str, ref: str) -> None:
    """Keep local source IDs closed over the Pages registry without losing lineage."""
    rows = manifest["assets"] + manifest.get("archived_sources", [])
    available_ids = {row["id"] for row in rows}
    repository_manifest = f"{repository_url.rstrip('/')}/blob/{quote(ref, safe='')}/assets/manifest.json"
    for row in rows:
        provenance = row.get("provenance", {})
        source_ids = provenance.get("source_ids", [])
        external_ids = [source_id for source_id in source_ids if source_id not in available_ids]
        if external_ids:
            provenance["source_ids"] = [source_id for source_id in source_ids if source_id in available_ids]
            provenance["repository_source_ids"] = provenance.get("repository_source_ids", []) + external_ids
        if provenance.get("repository_source_ids"):
            provenance["repository_manifest"] = repository_manifest


def publication_allowed(row: dict[str, object]) -> bool:
    """Fail closed on present metadata; absent records retain the legacy policy."""
    if "publication" not in row:
        return True
    publication = row["publication"]
    if not isinstance(publication, dict):
        return False
    if publication.get("authorization") != "maintainer-request":
        return False
    if not all(isinstance(publication.get(field), str) and publication[field].strip()
               for field in ("recorded_at", "evidence")):
        return False
    if publication.get("scope") == "brand-public-library":
        return publication.get("status") == "authorized"
    if publication.get("scope") == "brand-review-gallery":
        return (row.get("status") == "exploratory"
                and row.get("review_status") == "awaiting-review"
                and publication.get("canonical_selection") is False
                and publication.get("status", "authorized") == "authorized")
    return False


def inventory(root: Path, repository_url: str, ref: str) -> tuple[set[str], dict[str, object]]:
    manifest = json.loads(safe_file(root, "assets/manifest.json").read_text())
    selected: set[str] = set()
    rows = []
    for row in manifest["assets"]:
        relative = row["path"]
        if row["status"] not in ACTIVE_STATUSES:
            continue
        publication = row.get("publication", {})
        if not publication_allowed(row):
            continue
        if (row["status"] == "exploratory" and row.get("review_status") == "awaiting-review"
                and not (isinstance(publication, dict)
                         and publication.get("scope") == "brand-review-gallery"
                         and publication.get("authorization") == "maintainer-request")):
            continue
        if not (relative.startswith("assets/") or relative in PROFILE_APPLICATIONS):
            continue
        source = safe_file(root, relative)
        digest = hashlib.sha256(source.read_bytes()).hexdigest()
        if digest != row["sha256"]:
            raise BuildError(f"Asset changed after cataloging: {relative}")
        selected.add(relative)
        rows.append(row)
    for relative in ROOT_GUIDES:
        if (root / relative).exists():
            safe_file(root, relative)
            selected.add(relative)
    selected.update({"assets/README.md", "assets/manifest.json"})
    for source in (root / "docs/brand").iterdir():
        if source.suffix in {".html", ".md", ".json"}:
            selected.add(source.relative_to(root).as_posix())
    for name in TOKEN_FILES:
        if (root / "assets/tokens" / name).exists():
            selected.add(f"assets/tokens/{name}")
    public_fonts = root / "assets/brand/source"
    if public_fonts.exists():
        for source in public_fonts.rglob("*"):
            if source.is_symlink():
                raise BuildError("Public font sources must not contain symlinks")
            if source.is_file() and (source.suffix.lower() in PUBLIC_SOURCE_SUFFIXES or source.name in {"LICENSE", "OFL", "COPYING"}):
                selected.add(source.relative_to(root).as_posix())
    logo_builder = "scripts/build-brand-logos.py"
    if (root / logo_builder).exists():
        selected.add(logo_builder)
    for name in ("README.md", "README.pt-BR.md", "README.es.md"):
        if (root / "profile" / name).exists():
            selected.add(f"profile/{name}")
    for relative in selected:
        safe_file(root, relative)
    manifest["assets"] = rows
    scope_provenance(manifest, repository_url, ref)
    manifest["description"] += " Pages export: active public assets only; references remain in the repository."
    return selected, manifest


def rewrite_document(root: Path, owner: str, text: str, selected: set[str], repository_url: str, ref: str) -> str:
    def link(destination: str, resource: bool = False) -> str:
        target = resource_target(owner, destination) if resource else local_target(owner, destination)
        if target is None:
            return destination
        relative, parsed = target
        if relative in selected:
            return destination
        if resource:
            raise BuildError(f"Unpublished resource in {owner}: {destination}")
        return source_url(root, relative, parsed, repository_url, ref)

    def attribute(tag: str, name: str, destination: str | None) -> tuple[str, bool]:
        if destination is None:
            if is_resource(tag, name):
                raise BuildError(f"Empty resource attribute in {owner}: {name}")
            return name, False
        if name == "srcset":
            values = []
            for entry in destination.split(","):
                parts = entry.strip().split()
                if parts:
                    values.append(" ".join([link(parts[0], True), *parts[1:]]))
            if not values:
                raise BuildError(f"Empty resource in {owner}: srcset")
            replacement = ", ".join(values)
        elif name in {"href", "xlink:href", "src", "poster"} or (name == "data" and tag == "object"):
            replacement = link(destination, is_resource(tag, name))
        else:
            replacement = destination
        if name == "style":
            for reference in css_resources(owner, destination):
                link(reference, True)
        return f'{name}="{html.escape(replacement, quote=True)}"', replacement != destination

    class RewriteHTML(HTMLParser):
        def __init__(self, source: str):
            super().__init__(convert_charrefs=False)
            self.source = source
            self.offsets = [0]
            for match in re.finditer("\n", source):
                self.offsets.append(match.end())
            self.replacements = []
            self.in_style = False

        def handle_starttag(self, tag, attrs):
            check_embedded_document(tag, attrs, owner)
            self.in_style = tag == "style"
            original = self.get_starttag_text()
            values = dict(attrs)
            replacement = None
            if tag == "img":
                destination = values.get("src") or ""
                parsed = urlsplit(html.unescape(destination))
                target = local_target(owner, destination)
                if owner.endswith(".md") and (parsed.scheme or parsed.netloc):
                    # Markdown files are source downloads. Keep badge labels and
                    # their surrounding repository links without remote images.
                    replacement = html.escape(values.get("alt") or "External image")
                elif target and target[0].startswith("references/"):
                    destination = source_url(root, *target, repository_url, ref)
                    label = values.get("alt") or "Reference artwork"
                    replacement = f'<a href="{html.escape(destination, quote=True)}">View reference in the repository: {html.escape(label)}</a>'
            if replacement is None:
                rewritten = [attribute(tag, name, value) for name, value in attrs]
                if any(changed for _, changed in rewritten):
                    attributes = "".join(f" {value}" for value, _ in rewritten)
                    ending = " />" if original.rstrip().endswith("/>") else ">"
                    replacement = f"<{tag}{attributes}{ending}"
                else:
                    replacement = original
            line, column = self.getpos()
            start = self.offsets[line - 1] + column
            self.replacements.append((start, start + len(original), replacement))

        handle_startendtag = handle_starttag

        def handle_endtag(self, tag):
            if tag == "style":
                self.in_style = False

        def handle_data(self, data):
            if self.in_style:
                for reference in css_resources(owner, data):
                    link(reference, True)

        def rewrite(self):
            self.feed(self.source)
            self.close()
            result = self.source
            for start, end, replacement in reversed(self.replacements):
                result = result[:start] + replacement + result[end:]
            return result

    def markdown(match: re.Match[str]) -> str:
        image, label, destination, suffix = match.groups()
        target = local_target(owner, destination)
        parsed = urlsplit(html.unescape(destination))
        if image and (parsed.scheme or parsed.netloc):
            return f"[View external image: {label}]({link(destination)}{suffix})"
        if image and target and target[0].startswith("references/"):
            return f"[View reference in the repository: {label}]({link(destination)}{suffix})"
        return f"{image}[{label}]({link(destination, bool(image))}{suffix})"

    parts = CODE_FENCE.split(text) if owner.endswith(".md") else [text]
    for index in range(0, len(parts), 2):
        part = RewriteHTML(parts[index]).rewrite()
        if owner.endswith(".md"):
            part = MARKDOWN_LINK.sub(markdown, part)
        parts[index] = part
    return "".join(parts)


class DocumentLinks(HTMLParser):
    def __init__(self, owner: str):
        super().__init__()
        self.owner = owner
        self.in_style = False
        self.links: list[tuple[str, bool]] = []
        self.ids: set[str] = set()
    def handle_starttag(self, tag, attrs):
        check_embedded_document(tag, attrs, self.owner)
        self.in_style = tag == "style"
        for name, value in attrs:
            if name == "id":
                self.ids.add(value)
            elif name in {"href", "xlink:href", "src", "poster"} or (name == "data" and tag == "object"):
                self.links.append((value or "", is_resource(tag, name)))
            elif name == "srcset":
                self.links.extend((entry.strip().split()[0], True) for entry in (value or "").split(",") if entry.strip())
            elif name == "style":
                self.links.extend((reference, True) for reference in css_resources(self.owner, value or ""))

    handle_startendtag = handle_starttag

    def handle_endtag(self, tag):
        if tag == "style":
            self.in_style = False

    def handle_data(self, data):
        if self.in_style:
            self.links.extend((reference, True) for reference in css_resources(self.owner, data))


def check_output(output: Path) -> None:
    html_documents = {}
    for source in output.rglob("*.html"):
        parser = DocumentLinks(source.relative_to(output).as_posix())
        parser.feed(source.read_text())
        html_documents[source.relative_to(output).as_posix()] = parser
    for relative, parser in html_documents.items():
        for destination, resource in parser.links:
            parsed = urlsplit(html.unescape(destination))
            target = resource_target(relative, destination) if resource else local_target(relative, destination)
            target_name = target[0] if target else relative
            if parsed.scheme or parsed.netloc:
                continue
            safe_file(output, target_name)
            if parsed.fragment and target_name in html_documents:
                if unquote(parsed.fragment) not in html_documents[target_name].ids:
                    raise BuildError(f"Missing anchor in {relative}: {destination}")
    for source in output.rglob("*.css"):
        relative = source.relative_to(output).as_posix()
        for destination in css_resources(relative, source.read_text()):
            target = resource_target(relative, destination)
            safe_file(output, target[0])


def build(root: Path, output: Path, repository_url: str, ref: str) -> int:
    root = root.resolve()
    if output.is_symlink() or output.resolve() == root or output.resolve() in root.parents:
        raise BuildError("The destination must be a separate empty directory")
    if root in output.resolve().parents and output.resolve() != root / "_site":
        raise BuildError("Inside the checkout, only the _site destination is allowed")
    if output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise BuildError("Refusing to overwrite a nonempty destination; choose a new directory")
    selected, manifest = inventory(root, repository_url, ref)
    # Prepare transformations before writing, so missing media cannot leave a partial build.
    documents = {}
    for relative in selected:
        source = safe_file(root, relative)
        if source.suffix in {".html", ".md"}:
            documents[relative] = rewrite_document(root, relative, source.read_text(), selected, repository_url, ref)
        elif source.suffix == ".css":
            for destination in css_resources(relative, source.read_text()):
                target, _ = resource_target(relative, destination)
                if target not in selected:
                    raise BuildError(f"Unpublished resource in {relative}: {destination}")
    output.mkdir(parents=True, exist_ok=True)
    for relative in sorted(selected):
        destination = output / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        if relative in documents:
            destination.write_text(documents[relative])
        else:
            shutil.copyfile(safe_file(root, relative), destination)
    (output / "assets/manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
    (output / "index.html").write_text('''<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>PHP Fast Forward brand library</title><meta http-equiv="refresh" content="0; url=docs/brand/index.html"></head>
<body><p><a href="docs/brand/index.html">Open the PHP Fast Forward brand library</a></p></body></html>
''')
    (output / ".nojekyll").touch()
    check_output(output)
    return len(selected) + 2


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--repository-url", default="https://github.com/php-fast-forward/.github")
    parser.add_argument("--source-ref", default="main")
    args = parser.parse_args()
    if not args.repository_url.startswith("https://github.com/"):
        parser.error("--repository-url must name the public GitHub repository")
    try:
        count = build(args.root, args.output_dir, args.repository_url, args.source_ref)
    except (BuildError, OSError, KeyError, json.JSONDecodeError) as error:
        parser.exit(1, f"Pages build failed: {error}\n")
    print(f"Staged {count} public files in {args.output_dir}; links and anchors verified.")


if __name__ == "__main__":
    main()
