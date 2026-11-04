"""Write one HTML page per map and locale: the traced base image with the labels set in the site's type."""
import json, sys, os
HERE = os.path.dirname(os.path.abspath(__file__))
BUILD = os.path.join(os.path.dirname(HERE), '.build')
sys.path.insert(0, HERE); from spec import MAPS, WORDS, TYPES

CSS = open(os.path.join(HERE, 'overlay.css')).read()

def word(k, loc):
    if k in WORDS: return WORDS[k][loc]
    return k  # a literal: a depth, a proper name

def html(slug, loc):
    m = MAPS[slug]
    s = m['scale']; W, H = m['size']
    P = lambda x, y: f'left:{x * s:.1f}px;top:{y * s:.1f}px'
    out = [f'<!doctype html><html lang="{loc}"><head><meta charset="utf-8">',
           '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Instrument+Serif:ital@0;1&display=block" rel="stylesheet">',
           f'<style>{CSS}body{{width:{W}px;height:{H}px}}img.base{{width:{W}px;height:{H}px}}</style></head><body>',
           f'<img class="base" src="{os.path.abspath(m["base"])}">']
    if m.get('svg'): out.append(f'<svg viewBox="0 0 {W / s:.2f} {H / s:.2f}" width="{W}" height="{H}">{m["svg"]}</svg>')
    for L in m['labels']:
        t, x, y, k = L[:4]; o = L[4] if len(L) > 4 else {}
        txt = word(k, loc) if k else ''
        cls = o.get('cls', '')
        st = P(x, y) + ';' + o.get('style', '')
        if t == 'stop':
            out.append(f'<div class="p" style="{st}"><i class="stop"></i>' + (f'<span class="lab {cls}">{txt}</span>' if txt else '') + '</div>')
        elif t == 'home':
            out.append(f'<div class="p" style="{st}"><i class="home"></i><span class="homel {cls}">{txt}</span></div>')
        elif t == 'form':
            out.append(f'<div class="p" style="{st}"><i class="form"></i></div>')
        elif t == 'plat':
            out.append(f'<div class="p" style="{st}"><i class="plat" style="transform:rotate({o.get("rot", 0)}deg);{o.get("size", "")}"></i></div>')
        elif t == 'lab':
            out.append(f'<div class="p" style="{st}"><span class="lab u {cls}">{txt}</span></div>')
        elif t == 'pin':
            out.append(f'<div class="p" style="{st}"><i class="pin"></i><span class="lab {cls}">{txt}</span></div>')
        elif t == 'road':
            out.append(f'<div class="p" style="{st}"><span class="road" style="transform:translate(-50%,-50%) rotate({o.get("rot", 0)}deg)">{txt}</span></div>')
        else:  # zone, mang, d, name
            out.append(f'<div class="p" style="{st}"><span class="{t} {cls}">{txt}</span></div>')
    sub = ' · '.join([TYPES[m['type']][loc]] + ([m['aka']] if m.get('aka') else []) + ['Tulum'])
    tp = m.get('title_at', (44, 40))
    out.append(f'<div class="title" style="left:{tp[0]}px;top:{tp[1]}px"><h1>{m["title"]}</h1><p>{sub}</p></div>')
    if m.get('note'):
        out.append(f'<p class="note" style="{m["note_at"]}">{m["note"][loc]}</p>')
    if m.get('legend'):
        lp = m['legend_at']
        items = ''.join(f'<li><i class="k-{k}"></i>{WORDS[k][loc]}</li>' for k in m['legend'])
        out.append(f'<ul class="key" style="{lp}">{items}</ul>')
    if m.get('scale_m'):
        ppm, total, pos = m['scale_m']
        px = ppm * total * s
        ticks = ''.join(f'<span style="left:{px * i / 3:.1f}px">{round(total * i / 3)}' + (' m' if i == 3 else '') + '</span>' for i in range(4))
        out.append(f'<div class="scale" style="{pos};width:{px:.1f}px"><i style="width:{px:.1f}px"></i>{ticks}</div>')
    if m.get('north') is not None:
        out.append(f'<div class="north" style="{m["north_at"]};transform:rotate({m["north"]}deg)"><b style="transform:rotate({-m["north"]}deg)">N</b><i></i></div>')
    out.append(f'<div class="credit" style="{m.get("credit_at", "right:44px;top:46px")}">{m["credit"][loc] if isinstance(m["credit"], dict) else m["credit"]}</div>')
    out.append('</body></html>')
    return '\n'.join(out)

if __name__ == '__main__':
    os.makedirs(f'{BUILD}/html', exist_ok=True); os.makedirs(f'{BUILD}/out', exist_ok=True)
    jobs = []
    for slug in (sys.argv[1:] or MAPS):
        for loc in ('en', 'es', 'fr'):
            p = f'{BUILD}/html/{slug}-{loc}.html'
            open(p, 'w').write(html(slug, loc))
            jobs.append([os.path.abspath(p), f'{BUILD}/out/{slug}-{loc}.png', *MAPS[slug]['size']])
    json.dump(jobs, open(f'{BUILD}/jobs.json', 'w'))
