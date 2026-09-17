#!/usr/bin/env python3
"""Convert site raster images to WebP and update static references."""

from __future__ import annotations

import argparse
import json
from datetime import datetime
from pathlib import Path

from PIL import Image


IMAGE_EXTENSIONS = {".png", ".jpg", ".jpeg"}
TEXT_EXTENSIONS = {".html", ".css", ".js"}
IGNORED_PARTS = {"node_modules", ".git", ".next", "dist", "build", "__pycache__"}
WEBP_QUALITY = 85


def normalize_path(path: Path) -> str:
    return path.as_posix()


def is_ignored(path: Path) -> bool:
    return any(part in IGNORED_PARTS for part in path.parts)


def collect_files(root: Path, extensions: set[str]) -> list[Path]:
    return sorted(
        path
        for path in root.rglob("*")
        if path.is_file() and not is_ignored(path) and path.suffix.lower() in extensions
    )


def convert_image(image_path: Path, quality: int) -> Path:
    webp_path = image_path.with_suffix(".webp")
    with Image.open(image_path) as image:
        converted = image.convert("RGBA") if image.mode in {"RGBA", "LA"} else image.convert("RGB")
        converted.save(webp_path, "WEBP", quality=quality, method=6)
    if not webp_path.exists() or not webp_path.stat().st_size:
        raise RuntimeError(f"WebP conversion failed: {image_path}")
    return webp_path


def replacement_variants(root: Path, old_path: Path, new_path: Path) -> list[tuple[str, str]]:
    old_rel = normalize_path(old_path.relative_to(root))
    new_rel = normalize_path(new_path.relative_to(root))
    variants = [
        (old_rel, new_rel),
        (f"./{old_rel}", f"./{new_rel}"),
        (old_rel.replace(" ", "%20"), new_rel.replace(" ", "%20")),
        (f'./{old_rel.replace(" ", "%20")}', f'./{new_rel.replace(" ", "%20")}'),
        (old_path.name, new_path.name),
        (old_path.name.replace(" ", "%20"), new_path.name.replace(" ", "%20")),
        (old_rel.replace("/", "\\"), new_rel.replace("/", "\\")),
    ]
    return sorted(set(variants), key=lambda pair: len(pair[0]), reverse=True)


def update_references(root: Path, mappings: list[dict[str, object]]) -> list[str]:
    changed: list[str] = []
    for text_file in collect_files(root, TEXT_EXTENSIONS):
        encoding = "utf-8"
        try:
            content = text_file.read_text(encoding=encoding)
        except UnicodeDecodeError:
            encoding = "latin-1"
            content = text_file.read_text(encoding=encoding)
        original = content
        for mapping in mappings:
            old_path = Path(str(mapping["old_absolute_path"]))
            new_path = Path(str(mapping["new_absolute_path"]))
            for old_value, new_value in replacement_variants(root, old_path, new_path):
                content = content.replace(old_value, new_value)
        if content != original:
            text_file.write_text(content, encoding="utf-8")
            changed.append(normalize_path(text_file.relative_to(root)))
    return changed


def write_manifest(root: Path, images: list[Path]) -> Path:
    manifest = {
        "created_at": datetime.now().isoformat(timespec="seconds"),
        "root": normalize_path(root),
        "total_images": len(images),
        "images": [
            {
                "filename": image.name,
                "stem": image.stem,
                "extension": image.suffix,
                "relative_path": normalize_path(image.relative_to(root)),
                "absolute_path": normalize_path(image.resolve()),
                "target_webp_relative_path": normalize_path(image.with_suffix(".webp").relative_to(root)),
                "target_webp_absolute_path": normalize_path(image.with_suffix(".webp").resolve()),
                "size_bytes": image.stat().st_size,
            }
            for image in images
        ],
    }
    path = root / "webp-conversion-manifest.json"
    path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    return path


def run(root: Path, quality: int, dry_run: bool, delete_originals: bool) -> int:
    root = root.resolve()
    if not root.is_dir():
        raise ValueError(f"Root folder not found: {root}")
    if not 1 <= quality <= 100:
        raise ValueError("Quality must be between 1 and 100.")

    images = collect_files(root, IMAGE_EXTENSIONS)

    # A completed run is intentionally idempotent. Do not replace its audit trail
    # with an empty report when the command is re-run after originals were removed.
    if not images and not dry_run and (root / "webp-conversion-report.json").exists():
        print("No PNG/JPG/JPEG files found; keeping the existing conversion report.")
        return 0

    targets: dict[Path, Path] = {}
    for image in images:
        target = image.with_suffix(".webp")
        if target in targets or target.exists():
            raise RuntimeError(f"Refusing to overwrite WebP target: {target}")
        targets[target] = image

    manifest_path = write_manifest(root, images)
    print(f"Root: {root}\nFound images: {len(images)}\nManifest saved: {manifest_path}")
    if dry_run:
        for image in images:
            print(f"[DRY] {image.relative_to(root)} -> {image.with_suffix('.webp').relative_to(root)}")
        return 0

    mappings: list[dict[str, object]] = []
    failed: list[dict[str, str]] = []
    for image in images:
        try:
            webp = convert_image(image, quality)
            mappings.append(
                {
                    "old_filename": image.name,
                    "new_filename": webp.name,
                    "old_relative_path": normalize_path(image.relative_to(root)),
                    "new_relative_path": normalize_path(webp.relative_to(root)),
                    "old_absolute_path": normalize_path(image.resolve()),
                    "new_absolute_path": normalize_path(webp.resolve()),
                    "old_size_bytes": image.stat().st_size,
                    "new_size_bytes": webp.stat().st_size,
                }
            )
            print(f"[OK] {image.relative_to(root)} -> {webp.relative_to(root)}")
        except Exception as error:  # Keep processing and report every failed file.
            failed.append({"file": normalize_path(image.relative_to(root)), "error": str(error)})
            print(f"[FAILED] {image.relative_to(root)} | {error}")

    changed_files = update_references(root, mappings)
    deleted_files: list[str] = []
    if delete_originals:
        for mapping in mappings:
            old_path = Path(str(mapping["old_absolute_path"]))
            new_path = Path(str(mapping["new_absolute_path"]))
            if new_path.exists() and new_path.stat().st_size and old_path.exists():
                old_path.unlink()
                deleted_files.append(str(mapping["old_relative_path"]))

    report = {
        "created_at": datetime.now().isoformat(timespec="seconds"),
        "root": normalize_path(root),
        "quality": quality,
        "converted_count": len(mappings),
        "failed_count": len(failed),
        "updated_text_files_count": len(changed_files),
        "deleted_originals": delete_originals,
        "converted": mappings,
        "failed": failed,
        "updated_text_files": changed_files,
        "deleted_files": deleted_files,
    }
    report_path = root / "webp-conversion-report.json"
    report_path.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Updated references: {len(changed_files)}")
    print(f"Deleted originals: {len(deleted_files)}")
    print(f"Failed: {len(failed)}")
    print(f"Report saved: {report_path}")
    return 1 if failed else 0


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Convert PNG/JPG/JPEG images to WebP and update HTML/CSS/JS references.")
    parser.add_argument("--path", default=".", help="Root folder. Default: current folder")
    parser.add_argument("--quality", type=int, default=WEBP_QUALITY, help="WebP quality from 1 to 100. Default: 85")
    parser.add_argument("--dry-run", action="store_true", help="Only list what would happen.")
    parser.add_argument("--keep-originals", action="store_true", help="Keep source PNG/JPG/JPEG files.")
    arguments = parser.parse_args()
    raise SystemExit(run(Path(arguments.path), arguments.quality, arguments.dry_run, not arguments.keep_originals))
