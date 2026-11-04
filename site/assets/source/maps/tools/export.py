"""Cut the labelled maps in .build/out/ into what the site serves: AVIF and WebP at 800 px, 1x and 2x.

Files land in site/public/assets/maps/<slug>-v<version>-<locale>-<width>.<ext>;
the sizes and the version come from "plan" in content/cenotes.json. The site
caches images for a year as immutable (public-htaccess), so a map changed
after it has been published goes out under the next version: bump plan.v,
then run this.
"""
import json, os, sys
from PIL import Image
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from spec import MAPS

BUILD = os.path.join(os.path.dirname(HERE), '.build', 'out')
SITE = os.path.abspath(os.path.join(HERE, '..', '..', '..', '..'))
OUT = os.path.join(SITE, 'public', 'assets', 'maps')
PLANS = {c['slug']: c['plan'] for c in json.load(open(os.path.join(SITE, 'content', 'cenotes.json')))['cenotes'] if c.get('plan')}

def main(slugs):
    os.makedirs(OUT, exist_ok=True)
    for slug in slugs or MAPS:
        W, H = MAPS[slug]['size']
        plan = PLANS[slug]
        assert (plan['w'], plan['h']) == (W, H), f'{slug}: cenotes.json says {plan["w"]}x{plan["h"]}, the map is {W}x{H}'
        v = plan['v']
        for loc in ('en', 'es', 'fr'):
            im = Image.open(f'{BUILD}/{slug}-{loc}.png').convert('RGB')
            for w in (800, W, 2 * W):
                r = im if w == im.width else im.resize((w, round(H * w / W)), Image.LANCZOS)
                base = f'{OUT}/{slug}-v{v}-{loc}-{w}'
                r.save(base + '.webp', quality=84, method=6)
                r.save(base + '.avif', quality=64, speed=4)
        print(slug, W, H)

if __name__ == '__main__':
    main(sys.argv[1:])
