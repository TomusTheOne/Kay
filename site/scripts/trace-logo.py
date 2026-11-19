#!/usr/bin/env python3
"""
Vectorises Kay's logo, assets/source/brand/kay-diving-logo-source.jpg, into
the SVG kit beside it. scripts/brand.mjs then builds what the site serves.

    python3 scripts/trace-logo.py      (needs potrace, numpy, scipy, Pillow)

The source is a 1600x567 JPEG on navy. Each colour becomes one potrace layer,
traced from a 6x enlargement so the curves come out smooth, then stacked:

    cyan   the circle, waves, bubbles, tagline, rules and fish
    lens   the blue glass of the mask
    white  the diver and "Kay Diving"

The navy background is dropped: every file is transparent unless its name
says otherwise. The cyan's soft gradient in the JPEG becomes one flat tone,
which is what a logo needs at 16 px and on a T-shirt alike.
"""
import re
import subprocess
import tempfile
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage

DIR = Path(__file__).resolve().parent.parent / "assets" / "source" / "brand"
SRC = DIR / "kay-diving-logo-source.jpg"
S = 6                                      # enlargement before tracing

CYAN, LENS, WHITE, NAVY = "#08E3F6", "#106D98", "#FFFFFF", "#012D40"

im = Image.open(SRC).convert("RGB")
W, H = im.size
big = im.resize((W * S, H * S), Image.LANCZOS).filter(ImageFilter.GaussianBlur(1.6))
a = np.asarray(big).astype(np.float32)
R, G, B = a[..., 0], a[..., 1], a[..., 2]

# White first, grown a hair so it meets the cyan without a seam.
white = ndimage.binary_dilation((R > 140) & (G > 180) & (B > 180), iterations=1)
# Cyan is the ink whose red stays low: the pale edge pixels around white
# shapes have climbing red and belong to the white, not to a cyan halo.
cyan = ndimage.binary_dilation((B > 150) & (R < 110), iterations=3)
# The mask's glass, and only there: the same mid-blue rims every edge.
yy, xx = np.mgrid[0:H * S, 0:W * S]
lens = (B > 110) & (G < 150) & (R < 60) & (xx > 350 * S) & (xx < 480 * S) & (yy > 200 * S) & (yy < 290 * S)


def trace(mask):
    with tempfile.TemporaryDirectory() as tmp:
        pbm, svg = Path(tmp, "m.pbm"), Path(tmp, "m.svg")
        Image.fromarray(np.where(mask, 0, 255).astype(np.uint8)).convert("1").save(pbm)
        subprocess.run(["potrace", str(pbm), "-s", "-o", str(svg), "--turdsize", str(14 * S * S // 16),
                        "--alphamax", "1.1", "--opttolerance", "0.3"], check=True)
        text = svg.read_text()
    g = re.search(r'<g transform="([^"]+)"[^>]*>(.*?)</g>', text, re.S)
    return g.group(1), " ".join(re.findall(r'd="([^"]+)"', g.group(2)))


MASKS = (("cyan", cyan), ("lens", lens), ("white", white))
# The whole lockup, and the emblem on its own: traced apart, so an icon file
# carries the emblem's curves and not the wordmark's, hidden off-canvas.
LOCKUP_LAYERS = {name: trace(mask) for name, mask in MASKS}
EMBLEM_LAYERS = {name: trace(mask & (xx < 545 * S)) for name, mask in MASKS}


def layer(layers, name, fill):
    transform, d = layers[name]
    return f'<path fill="{fill}" transform="scale({1 / S:.6f}) {transform}" d="{d}"/>'


def svg(viewbox, *, white=WHITE, background=None, pad=None, radius=0, title="Kay Diving"):
    layers = LOCKUP_LAYERS if viewbox == LOCKUP else EMBLEM_LAYERS
    """viewbox in source pixels. pad: a square canvas around it, as a share of its side."""
    x, y, w, h = viewbox
    if pad is not None:
        side = max(w, h) / (1 - 2 * pad)
        x, y, w, h = x - (side - w) / 2, y - (side - h) / 2, side, side
    bg = (f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" rx="{radius * w:.1f}" fill="{background}"/>'
          if background else "")
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{x:.1f} {y:.1f} {w:.1f} {h:.1f}" '
            f'role="img"><title>{title}</title>{bg}'
            f'{layer(layers, "cyan", CYAN)}{layer(layers, "lens", LENS)}{layer(layers, "white", white)}</svg>\n')


LOCKUP = (156, 84, 1380, 374)       # emblem, wordmark, tagline and fish, tight
EMBLEM = (157, 82, 372, 374)        # the diver in the circle, with its bubbles

FILES = {
    "kay-diving-logo.svg":          svg(LOCKUP),                                   # on dark
    "kay-diving-logo-light.svg":    svg(LOCKUP, white=NAVY),                       # on light
    "kay-diving-logo-navy.svg":     svg(LOCKUP, background=NAVY, pad=None),        # with its ground
    "kay-diving-icon.svg":          svg(EMBLEM, pad=0.0),                          # on dark
    "kay-diving-icon-light.svg":    svg(EMBLEM, white=NAVY, pad=0.0),              # on light
    "favicon.svg":                  svg(EMBLEM, background=NAVY, pad=0.06, radius=0.22),
    "app-icon.svg":                 svg(EMBLEM, background=NAVY, pad=0.12),
    "icon-maskable.svg":            svg(EMBLEM, background=NAVY, pad=0.22),        # inside the 80% safe zone
}
for name, text in FILES.items():
    (DIR / name).write_text(text)
    print(f"  {name:28} {len(text) // 1024:>3} KB")
