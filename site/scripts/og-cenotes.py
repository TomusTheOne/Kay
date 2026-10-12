"""Social cards for the cenote pages: 1200x630 JPEG, what WhatsApp and the crawlers ask for.

A cenote with a photograph gets one card cut from it, the same in every
language. One without a photograph but with a dive map gets the map, cut once
per language since the map carries words. The rule is the same as
socialCard() in app/[locale]/cenotes/[slug]/page.tsx.

    python3 scripts/og-cenotes.py        (from site/, needs Pillow)
"""
import json
from pathlib import Path
from PIL import Image

SITE = Path(__file__).resolve().parent.parent
PUB = SITE / "public" / "assets"
OUT = PUB / "og" / "cenotes"
W, H = 1200, 630


def cover(im, top=0.5):
    """Scale to cover 1200x630, then crop; `top` is where the crop sits vertically (0 top, 1 bottom)."""
    k = max(W / im.width, H / im.height)
    im = im.convert("RGB").resize((round(im.width * k), round(im.height * k)), Image.LANCZOS)
    x = (im.width - W) // 2
    y = round((im.height - H) * top)
    return im.crop((x, y, x + W, y + H))


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    data = json.loads((SITE / "content" / "cenotes.json").read_text())
    for c in data["cenotes"]:
        if c["photo"]:
            cover(Image.open(PUB / "photos" / f"{c['photo']}.webp")).save(OUT / f"{c['slug']}.jpg", quality=86, optimize=True, progressive=True)
            print(c["slug"])
        elif c.get("plan"):
            for loc in data.get("locales", ["en", "es", "fr"]):
                src = PUB / "maps" / f"{c['slug']}-{loc}-{c['plan']['w'] * 2}.webp"
                cover(Image.open(src), top=0.0).save(OUT / f"{c['slug']}-{loc}.jpg", quality=86, optimize=True, progressive=True)
                print(c["slug"], loc)


if __name__ == "__main__":
    main()
