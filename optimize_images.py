"""
Image optimization script for Multibiz website.
Converts large PNG/JPG images to WebP format and resizes them.
Run: python optimize_images.py
"""
import os
from pathlib import Path
from PIL import Image

STATIC_IMAGES = Path(__file__).parent / "static" / "images"

# Max dimensions per image category
LIMITS = {
    # Landing page hero/feature images (big but need quality)
    "picture1.png": (1400, 800),
    "picture2.png": (1400, 800),
    "picture3.png": (1400, 800),
    "S1.png":       (1400, 800),
    "S2.png":       (1400, 800),
    # Service section images
    "service1.png": (900, 600),
    "service2.png": (900, 600),
    "service3.png": (900, 600),
    "service4.png": (900, 600),
    # Apply now banner
    "applynow.png": (900, 600),
    # Map
    "map.png":      (800, 600),
    # Logo
    "mbLogo.png":   (300, 120),
    # Brand logos -- keep small
    "b6.png":       (300, 200),
    "bnine.png":    (300, 200),
    "b24.png":      (300, 200),
    "b25.png":      (300, 200),
    "pic1.jpg":     (800, 600),
}

DEFAULT_MAX = (800, 600)
WEBP_QUALITY = 82


def optimize_image(src, max_size):
    """Convert an image to WebP, resize if needed, and save alongside the original."""
    try:
        with Image.open(src) as img:
            orig_size = src.stat().st_size
            # Convert RGBA/P/LA for WebP transparency support
            if img.mode in ("RGBA", "P", "LA"):
                img = img.convert("RGBA")
            else:
                img = img.convert("RGB")

            # Resize if oversized
            img.thumbnail(max_size, Image.LANCZOS)

            dest = src.with_suffix(".webp")
            img.save(dest, "WEBP", quality=WEBP_QUALITY, method=6)
            new_size = dest.stat().st_size
            pct = (1 - new_size / orig_size) * 100
            print("  [OK] %s -> %s  %dKB -> %dKB  (-%.0f%%)" % (
                src.name, dest.name, orig_size // 1024, new_size // 1024, pct))
    except Exception as exc:
        print("  [ERR] %s: %s" % (src.name, exc))


def main():
    print("[SCAN] Scanning images in", STATIC_IMAGES)
    targets = list(STATIC_IMAGES.glob("*.png")) + list(STATIC_IMAGES.glob("*.jpg"))
    print("   Found %d image(s) to process.\n" % len(targets))

    for img_path in sorted(targets):
        max_size = LIMITS.get(img_path.name, DEFAULT_MAX)
        optimize_image(img_path, max_size)

    print("\n[DONE] Original files are kept. Templates will use .webp versions.")


if __name__ == "__main__":
    main()
