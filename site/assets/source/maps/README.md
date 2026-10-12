# Cenote dive maps

The six dive maps on the cenote pages (`public/assets/maps/`), redrawn in the
palette of the site's cenote map (`components/CenoteMap.tsx`) from the
originals in `originals/`. The shape of each cenote is traced from its
original, not redrawn by hand, so passages and cavern limits stay where the
source puts them.

| Cenote | Original | View |
|---|---|---|
| Casa Cenote | Gerardo Aizpuru © 2004, Underwater Editions | plan |
| Car Wash (Aktun Ha) | Underwater Editions | plan |
| Dos Ojos | Sebastián Maza © 2011, Fisheye Adventures | plan |
| Dreamgate | dive-centre map, author not given | plan |
| El Pit | Alejandro Elizondo, Underwater Editions | section |
| Angelita | Underwater Expeditions México | section |

The credit is printed on every map and under it on the page
(`plan.credit` in `content/cenotes.json`). These are third-party drawings:
ask the authors before relying on them commercially.

## How a map is made

1. **Trace** (`tools/masks.py`, `plan.py`, `section.py`, `casa*.py`): the water
   of the original becomes a mask, then a flat render in the site's colours —
   `base/<slug>.png`. Committed, so step 2 never needs the tracer.
2. **Label** (`tools/spec.py`, `overlay.py`, `overlay.css`, `shoot.mjs`): every
   label, depth and marker is placed in the original's pixel coordinates in
   `spec.py`, with its words in English, Spanish and French, and set in the
   site's own type in a headless Chromium.
3. **Export** (`tools/export.py`): AVIF and WebP at 800 px, 1× and 2×.

```bash
tools/build.sh            # after changing a label or a translation
tools/build.sh --trace    # after changing a tracer
```

Needs Python 3 with Pillow, NumPy, SciPy and opencv-python-headless, Node
with Playwright (set CHROMIUM_PATH to use a browser outside Playwright's own),
and network access for Google Fonts. The build is
deterministic: an unchanged spec gives byte-identical files.

Images are cached for a year as immutable (`public-htaccess`): once a map is
live, a change must go out under a new file name. The social card of a cenote
without a photograph is cut from its map by `scripts/og-cenotes.py` — rerun it
after a rebuild.
