#!/usr/bin/env python3
"""
Builds every photo the site serves, from the full-resolution originals in
assets/source/, into AVIF + WebP at the exact size each slot renders at.

Run after adding or replacing a source:

    python3 scripts/photos.py

The originals are Kay's own, at 2500-2800 px. Before them the site ran on
Instagram exports at 861 px, which is why the hero looked soft on a laptop
and the Open Graph card had to be upscaled.

Each slot names its source, its output size, and a focal point — the part of
the frame that must survive the crop, as a fraction of width and height.
Centre-cropping a photograph is how you decapitate the diver.
"""
from PIL import Image
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "assets" / "source"
OUT = ROOT / "public" / "assets" / "photos"

# slot: (source, width, height, focal x, focal y)
SLOTS = {
    # The hero is the one image people see before anything else.
    "hero":          ("angelita",  1920, 1185, 0.50, 0.50),
    # Same frame, cut much wider for the band that breaks up the page.
    "band":          ("angelita",  1720,  882, 0.52, 0.46),

    "card-casa":     ("casa",       960,  660, 0.50, 0.55),
    "card-angelita": ("mangrove",   960,  660, 0.48, 0.50),
    "guide":         ("guide",      900, 1125, 0.50, 0.45),

    # Casa Cenote's green water — the freshwater half of Reef & Cenote.
    "card-reef":     ("casa-green",  960,  660, 0.42, 0.58),
    # Light through the mangrove roots: what you see with your face in the
    # water and no tank on your back.
    "card-snorkel":  ("cenote-light", 960, 660, 0.50, 0.46),
    # Deep, dark, a stage bottle on the diver's side. The Advanced card was
    # the last one still showing a drawing.
    "card-advanced": ("deep-stage",   960, 660, 0.50, 0.62),
    # The bull sharks are a product now, so the photograph moves out of the
    # gallery and onto the card that sells the dive.
    "card-sharks":   ("sharks",       960, 660, 0.52, 0.55),
    # The gallery, carrying only photographs the page has not used above.
    # Five tiles is what the grid was drawn for: one tall on the left, four
    # filling the two columns beside it.
    "gallery-1":     ("deep-descent",      880, 1180, 0.50, 0.50),  # la tuile haute
    "gallery-2":     ("cavern-silhouette", 900,  600, 0.52, 0.48),
    "gallery-3":     ("cavern-posts",      900,  600, 0.50, 0.52),
    "gallery-4":     ("sharks-light",     1320,  566, 0.50, 0.42),  # large, sur deux colonnes
}


def crop_to(im: Image.Image, w: int, h: int, fx: float, fy: float) -> Image.Image:
    """Cover-crop around a focal point, then resize. Never upscales silently."""
    target = w / h
    sw, sh = im.size
    if sw / sh > target:                      # source too wide: trim the sides
        cw, ch = int(round(sh * target)), sh
    else:                                     # too tall: trim top and bottom
        cw, ch = sw, int(round(sw / target))
    left = min(max(int(round(sw * fx - cw / 2)), 0), sw - cw)
    top = min(max(int(round(sh * fy - ch / 2)), 0), sh - ch)
    box = im.crop((left, top, left + cw, top + ch))
    if cw < w:
        print(f"    ! only {cw}px of source for a {w}px slot — it will be soft")
    return box.resize((w, h), Image.LANCZOS)


def main() -> int:
    if not SRC.is_dir():
        print(f"no sources in {SRC}", file=sys.stderr)
        return 1
    OUT.mkdir(parents=True, exist_ok=True)
    total = 0
    for slot, (name, w, h, fx, fy) in SLOTS.items():
        matches = sorted(SRC.glob(f"{name}.*"))
        if not matches:
            print(f"  MISSING source '{name}' for slot '{slot}'", file=sys.stderr)
            return 1
        im = Image.open(matches[0]).convert("RGB")
        out = crop_to(im, w, h, fx, fy)
        # AVIF first for the browsers that take it, WebP for the rest.
        out.save(OUT / f"{slot}.avif", quality=62, speed=4)
        out.save(OUT / f"{slot}.webp", quality=80, method=6)
        a = (OUT / f"{slot}.avif").stat().st_size
        b = (OUT / f"{slot}.webp").stat().st_size
        total += a + b
        print(f"  {slot:<16} {w}x{h}  avif {a//1024:>4} KB  webp {b//1024:>4} KB   <- {matches[0].name}")
    print(f"\n  {len(SLOTS)} slots, {total//1024} KB in total")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
