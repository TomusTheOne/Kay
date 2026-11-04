"""Render a top-down cenote map in the site map's palette (components/CenoteMap.tsx INK)."""
import os, sys, numpy as np, cv2
from PIL import Image
from scipy import ndimage as nd
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); import masks

hx = lambda h: np.array([int(h[i:i + 2], 16) for i in (1, 3, 5)], float)
INK = dict(sea=hx('#03111A'), land=hx('#0A1D26'), green=hx('#0F2E30'), town=hx('#10262F'),
           water=hx('#0B4450'), open=hx('#136571'), turq=hx('#4FE0D2'), glow=hx('#A9F5EC'), foam=hx('#E6FBF6'))

def over(img, col, alpha):
    alpha = np.clip(alpha, 0, 1)[..., None]
    return img * (1 - alpha) + col * alpha

def render(name, src, out, FW=1600, band_m=30, line_thin=False, lite_a=.14):
    a, w, op, ln, lite = getattr(masks, name)(src)
    sh, sw = w.shape
    W2 = FW * 2; H2 = round(sh * W2 / sw)
    k = W2 / sw
    def up(m, blur):
        x = cv2.resize(m.astype(np.uint8) * 255, (W2, H2), interpolation=cv2.INTER_LINEAR).astype(float) / 255
        return cv2.GaussianBlur(x, (0, 0), blur) if blur else x
    W = up(w, k * 1.5) > .5
    O = (up(op, k * 1.2) > .5) & W if op is not None else None
    img = np.zeros((H2, W2, 3)) + INK['land']
    d = nd.distance_transform_edt(~W)
    img = over(img, INK['green'], np.clip(1 - d / (band_m * k / 4), 0, 1) ** .6 * .95)
    img = over(img, INK['water'], W * 1.0)
    if O is not None:
        img = over(img, INK['open'], cv2.GaussianBlur(O.astype(float), (0, 0), 2))
        e = (O ^ nd.binary_erosion(O, iterations=3)).astype(float)
        img = over(img, INK['glow'], cv2.GaussianBlur(e, (0, 0), 1.2) * .28)
    if lite is not None:
        Lt = cv2.GaussianBlur(up(lite, 0), (0, 0), k * .45) * nd.binary_erosion(W, iterations=int(k * 3))
        img = over(img, INK['foam'], np.clip(Lt * 1.8, 0, 1) * lite_a)
    if ln is not None:
        thin = nd.binary_erosion(ln, iterations=1) if line_thin else ln
        L = cv2.GaussianBlur(up(thin & w, 0), (0, 0), k * .35) * nd.binary_erosion(W, iterations=int(k * 2))
        L = np.clip(L * 2.2, 0, 1)
        img = over(img, INK['glow'], cv2.GaussianBlur(L, (0, 0), 6) * (.18 if line_thin else .35))
        img = over(img, INK['glow'], L * (.75 if line_thin else .9))
    edge = (W ^ nd.binary_erosion(W, iterations=4)).astype(float)
    img = over(img, INK['turq'], np.clip(cv2.GaussianBlur(edge, (0, 0), 18) * 9, 0, 1) * .20)
    img = over(img, INK['turq'], cv2.GaussianBlur(edge, (0, 0), 1.3) * .85)
    yy, xx = np.mgrid[0:H2, 0:W2]; v = ((xx / W2 - .5) ** 2 + (yy / H2 - .5) ** 2) ** .5
    img = over(img, INK['sea'], np.clip((v - .45) * 1.4, 0, .5))
    Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).resize((FW, H2 // 2), Image.LANCZOS).save(out)
    print(out, FW, H2 // 2, 'scale', FW / sw)

if __name__ == '__main__':
    kw = dict(a.split('=') for a in sys.argv[4:])
    render(sys.argv[1], sys.argv[2], sys.argv[3], line_thin=kw.get('thin') == '1', lite_a=float(kw.get('lite', .14)))
